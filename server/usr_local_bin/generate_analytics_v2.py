#!/usr/bin/env python3
"""
🚖 Daily Auto Call Center Analytics - Greek Version
Daily analytics with stunning HTML reports and interactive visualizations
"""

import json
import re
import sys
import math
import os
from datetime import datetime, timedelta
from collections import Counter, defaultdict
from typing import List, Dict, Any, Optional, Tuple
import statistics
import logging

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler('/tmp/taxi_analytics.log'),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger(__name__)

class TaxiAnalyticsEngine:
    """Main analytics engine for daily taxi call data processing."""
    
    def __init__(self, config: Dict[str, Any] = None):
        """Initialize the analytics engine with configuration from analytics.json only."""
        self.config = self._load_config_from_json_only(config)
        self.calls = []
        self.analytics = {}
        logger.info(f"Analytics engine initialized with config from: {self.config.get('config_source', 'analytics.json')}")
        
    def _load_config_from_json_only(self, override_config: Dict[str, Any] = None) -> Dict[str, Any]:
        """Load configuration ONLY from analytics.json file - no defaults."""
        config_file = 'analytics.json'
        
        # Check if analytics.json exists
        if not os.path.exists(config_file):
            error_msg = f"❌ ΣΦΑΛΜΑ: Το αρχείο {config_file} δεν βρέθηκε!"
            logger.error(error_msg)
            print(error_msg)
            print("Παρακαλώ δημιουργήστε το αρχείο analytics.json με τις απαραίτητες ρυθμίσεις.")
            sys.exit(1)
        
        # Try to load from JSON file
        try:
            with open(config_file, 'r', encoding='utf-8') as f:
                config = json.load(f)
                config['config_source'] = f'analytics.json file'
                logger.info(f"Configuration loaded successfully from {config_file}")
                
                # Validate required sections exist
                required_sections = ['company', 'taxi_rates', 'analysis', 'output', 'ui']
                missing_sections = [section for section in required_sections if section not in config]
                
                if missing_sections:
                    error_msg = f"❌ ΣΦΑΛΜΑ: Λείπουν απαραίτητες ενότητες στο {config_file}: {missing_sections}"
                    logger.error(error_msg)
                    print(error_msg)
                    sys.exit(1)
                
                # Validate required company fields
                required_company_fields = ['name', 'title', 'subtitle', 'footer_text']
                missing_company_fields = [field for field in required_company_fields 
                                        if field not in config.get('company', {})]
                
                if missing_company_fields:
                    error_msg = f"❌ ΣΦΑΛΜΑ: Λείπουν απαραίτητα πεδία στην ενότητα 'company': {missing_company_fields}"
                    logger.error(error_msg)
                    print(error_msg)
                    sys.exit(1)
                
                # Validate required taxi_rates fields
                required_taxi_fields = ['base_fare_day', 'per_km_rate_day', 'base_fare_night', 
                                      'per_km_rate_night', 'minimum_fare', 'route_factor', 
                                      'night_hours_start', 'night_hours_end']
                missing_taxi_fields = [field for field in required_taxi_fields 
                                     if field not in config.get('taxi_rates', {})]
                
                if missing_taxi_fields:
                    error_msg = f"❌ ΣΦΑΛΜΑ: Λείπουν απαραίτητα πεδία στην ενότητα 'taxi_rates': {missing_taxi_fields}"
                    logger.error(error_msg)
                    print(error_msg)
                    sys.exit(1)
                
                # Validate required analysis fields
                required_analysis_fields = ['frequent_customer_threshold', 'regular_customer_threshold', 
                                          'min_distance_km', 'max_distance_km', 'earth_radius_km']
                missing_analysis_fields = [field for field in required_analysis_fields 
                                         if field not in config.get('analysis', {})]
                
                if missing_analysis_fields:
                    error_msg = f"❌ ΣΦΑΛΜΑ: Λείπουν απαραίτητα πεδία στην ενότητα 'analysis': {missing_analysis_fields}"
                    logger.error(error_msg)
                    print(error_msg)
                    sys.exit(1)
                
                logger.info("✅ Όλες οι απαραίτητες ρυθμίσεις βρέθηκαν στο analytics.json")
                
        except json.JSONDecodeError as e:
            error_msg = f"❌ ΣΦΑΛΜΑ JSON: Το αρχείο {config_file} περιέχει μη έγκυρο JSON: {e}"
            logger.error(error_msg)
            print(error_msg)
            sys.exit(1)
        except Exception as e:
            error_msg = f"❌ ΣΦΑΛΜΑ: Αδυναμία φόρτωσης {config_file}: {e}"
            logger.error(error_msg)
            print(error_msg)
            sys.exit(1)
        
        # Apply any override config
        if override_config:
            config.update(override_config)
            logger.info("Override configuration applied")
            
        return config

    def haversine_distance(self, lat1: float, lon1: float, lat2: float, lon2: float) -> float:
        """Calculate the great circle distance between two points in kilometers."""
        try:
            lat1, lon1, lat2, lon2 = map(math.radians, [lat1, lon1, lat2, lon2])
            dlat = lat2 - lat1
            dlon = lon2 - lon1
            a = math.sin(dlat/2)**2 + math.cos(lat1) * math.cos(lat2) * math.sin(dlon/2)**2
            c = 2 * math.asin(math.sqrt(a))
            return c * self.config['analysis']['earth_radius_km']
        except (ValueError, TypeError) as e:
            logger.warning(f"Error calculating distance: {e}")
            return 0.0

    def _is_night_time(self, timestamp: datetime) -> bool:
        """Determine if a timestamp falls within night hours using configurable times."""
        taxi_rates = self.config['taxi_rates']
        night_start = taxi_rates['night_hours_start']
        night_end = taxi_rates['night_hours_end']
        
        hour = timestamp.hour
        
        # Handle night hours that span midnight (e.g., 22:00-06:00)
        if night_start > night_end:
            return hour >= night_start or hour < night_end
        else:
            # Handle night hours within same day (e.g., 01:00-05:00)
            return night_start <= hour < night_end

    def parse_log_file(self, file_path: str) -> List[Dict[str, Any]]:
        """Parse the log file and extract API call data with enhanced error handling."""
        calls = []
        
        try:
            if not os.path.exists(file_path):
                logger.error(f"File not found: {file_path}")
                return calls
            
            with open(file_path, 'r', encoding='utf-8') as file:
                lines = file.readlines()
                
                for line_num, line in enumerate(lines):
                    try:
                        if 'Φορτίο API:' in line:
                            # Extract timestamp - handle both formats with and without microseconds
                            timestamp_match = re.match(r'(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(?:,\d{3})?)', line)
                            if timestamp_match:
                                timestamp_str = timestamp_match.group(1)
                                try:
                                    # Try with microseconds format first
                                    if ',' in timestamp_str:
                                        # Convert comma to dot for microseconds
                                        timestamp_str_fixed = timestamp_str.replace(',', '.')
                                        timestamp = datetime.strptime(timestamp_str_fixed, '%Y-%m-%d %H:%M:%S.%f')
                                    else:
                                        # Try without microseconds
                                        timestamp = datetime.strptime(timestamp_str, self.config['output']['date_format'])
                                except ValueError as e:
                                    logger.warning(f"Timestamp parsing error at line {line_num}: {e}")
                                    continue
                            else:
                                logger.warning(f"No timestamp found at line {line_num}")
                                continue
                            
                            # Extract JSON data from request
                            json_start = line.find('{"callTimeStamp"')
                            if json_start != -1:
                                json_str = line[json_start:].strip()
                                try:
                                    call_data = json.loads(json_str)
                                    call_data['logTimestamp'] = timestamp
                                    call_data['lineNumber'] = line_num
                                    
                                    # Look for the corresponding API response in the next few lines
                                    response_data = self._find_api_response(lines, line_num)
                                    if response_data:
                                        # Merge response data into call data
                                        if 'response' in response_data and response_data['response']:
                                            call_data['isReservation'] = response_data['response'].get('isReservation', False)
                                            call_data['callId'] = response_data['response'].get('id')
                                            call_data['discountApplied'] = response_data['response'].get('discountApplied', 0.0)
                                        else:
                                            # Handle cases where response is None (errors)
                                            call_data['isReservation'] = False
                                            call_data['callId'] = None
                                            call_data['discountApplied'] = 0.0
                                            
                                        # Add result information
                                        if 'result' in response_data:
                                            call_data['resultCode'] = response_data['result'].get('resultCode', 0)
                                            call_data['resultMessage'] = response_data['result'].get('result', 'UNKNOWN')
                                    else:
                                        # Default values if no response found
                                        call_data['isReservation'] = False
                                        call_data['callId'] = None
                                        call_data['discountApplied'] = 0.0
                                        call_data['resultCode'] = 0
                                        call_data['resultMessage'] = 'UNKNOWN'
                                    
                                    calls.append(call_data)
                                    
                                except json.JSONDecodeError as e:
                                    logger.warning(f"JSON parsing error at line {line_num}: {e}")
                                    continue
                    except Exception as e:
                        logger.warning(f"Error processing line {line_num}: {e}")
                        continue
        
        except Exception as e:
            logger.error(f"Error reading file {file_path}: {e}")
            return calls
        
        logger.info(f"Successfully parsed {len(calls)} calls from {file_path}")
        return calls

    def _find_api_response(self, lines: List[str], request_line_num: int) -> Dict[str, Any]:
        """Find the API response corresponding to a request."""
        # Look for the response in the next few lines (typically within 5 lines)
        for i in range(request_line_num + 1, min(request_line_num + 8, len(lines))):
            line = lines[i]
            if 'Απάντηση API:' in line:
                json_start = line.find('{"restrictionID"')
                if json_start != -1:
                    json_str = line[json_start:].strip()
                    try:
                        response_data = json.loads(json_str)
                        return response_data
                    except json.JSONDecodeError:
                        continue
        return {}

    def analyze_advanced_metrics(self, calls: List[Dict]) -> Dict:
        """Advanced analytics with deeper insights for daily analysis."""
        logger.info("Starting daily advanced metrics analysis...")
        
        if not calls:
            logger.warning("No calls data provided for advanced metrics")
            return {}
        
        # Time-based analysis for daily data
        timestamps = [call['logTimestamp'] for call in calls]
        date_range = max(timestamps) - min(timestamps)
        
        logger.info(f"Analyzing {len(calls)} calls over {date_range.total_seconds()/3600:.1f} hours today")
        
        # Hourly patterns with detailed breakdown
        hourly_data = defaultdict(list)
        for call in calls:
            hour = call['logTimestamp'].hour
            hourly_data[hour].append(call)
        
        # Calculate efficiency metrics
        logger.info("Calculating daily efficiency metrics...")
        efficiency_metrics = self._calculate_efficiency_metrics(calls)
        
        # Geographic clustering
        logger.info("Analyzing geographic patterns...")
        geographic_insights = self._analyze_geographic_patterns(calls)
        
        # Customer segmentation
        logger.info("Analyzing customer behavior...")
        customer_insights = self._analyze_customer_behavior(calls)
        
        # Revenue estimation (if pricing data available)
        logger.info("Estimating daily revenue metrics...")
        revenue_insights = self._estimate_revenue_metrics(calls)
        
        logger.info("Daily advanced metrics analysis complete")
        
        return {
            'time_analysis': {
                'total_hours': date_range.total_seconds() / 3600,
                'calls_per_hour': len(calls) / max(1, date_range.total_seconds() / 3600),
                'hourly_breakdown': {str(h): len(calls) for h, calls in hourly_data.items()},
                'peak_hours': sorted(hourly_data.keys(), key=lambda x: len(hourly_data[x]), reverse=True)[:3],
                'busiest_hour': max(hourly_data.keys(), key=lambda x: len(hourly_data[x])) if hourly_data else None
            },
            'efficiency_metrics': efficiency_metrics,
            'geographic_insights': geographic_insights,
            'customer_insights': customer_insights,
            'revenue_insights': revenue_insights
        }

    def _calculate_efficiency_metrics(self, calls: List[Dict]) -> Dict:
        """Calculate operational efficiency metrics for daily analysis."""
        logger.info("Calculating daily efficiency metrics...")
        
        if len(calls) < 2:
            logger.info("Not enough calls for efficiency metrics calculation")
            return {'avg_response_time': 0, 'utilization_rate': 0}
        
        # Sort calls by timestamp
        sorted_calls = sorted(calls, key=lambda x: x['logTimestamp'])
        
        # Calculate response times between calls
        response_times = []
        for i in range(1, len(sorted_calls)):
            time_diff = (sorted_calls[i]['logTimestamp'] - sorted_calls[i-1]['logTimestamp']).total_seconds() / 60
            response_times.append(time_diff)
        
        # Calculate daily utilization rate
        total_time = (sorted_calls[-1]['logTimestamp'] - sorted_calls[0]['logTimestamp']).total_seconds() / 3600
        active_periods = len([t for t in response_times if t < self.config['analysis']['utilization_time_window_minutes']])
        utilization_rate = (active_periods / max(1, len(response_times))) * 100
        
        logger.info(f"Daily efficiency metrics calculated: {len(response_times)} intervals, {utilization_rate:.1f}% utilization")
        
        return {
            'avg_response_time': statistics.mean(response_times) if response_times else 0,
            'median_response_time': statistics.median(response_times) if response_times else 0,
            'min_response_time': min(response_times) if response_times else 0,
            'max_response_time': max(response_times) if response_times else 0,
            'utilization_rate': min(100, utilization_rate),
            'total_active_hours': total_time
        }

    def _analyze_geographic_patterns(self, calls: List[Dict]) -> Dict:
        """Analyze geographic patterns and hotspots."""
        logger.info("Analyzing geographic patterns...")
        
        valid_coords = []
        pickup_coords = []
        
        for call in calls:
            pickup_lat = call.get('latitude')
            pickup_lng = call.get('longitude')
            
            if all([pickup_lat, pickup_lng]) and all([coord != 0 for coord in [pickup_lat, pickup_lng]]):
                pickup_coords.append((pickup_lat, pickup_lng))
                valid_coords.append((pickup_lat, pickup_lng))
        
        logger.info(f"Found {len(valid_coords)} valid coordinates")
        
        if not valid_coords:
            logger.warning("No valid coordinates found")
            return {'coverage_area': 0, 'total_coordinates': 0}
        
        # Calculate coverage area
        lats, lngs = zip(*valid_coords)
        lat_range = max(lats) - min(lats)
        lng_range = max(lngs) - min(lngs)
        coverage_area = lat_range * lng_range * 111.32 * 111.32  # Approximate km²
        
        logger.info(f"Geographic analysis complete: {coverage_area:.2f} km² coverage area")
        
        return {
            'coverage_area_km2': coverage_area,
            'total_coordinates': len(valid_coords),
            'pickup_locations': len(pickup_coords),
            'center_lat': statistics.mean(lats),
            'center_lng': statistics.mean(lngs)
        }

    def _analyze_customer_behavior(self, calls: List[Dict]) -> Dict:
        """Analyze customer behavior patterns for daily data using configurable thresholds."""
        logger.info("Analyzing daily customer behavior patterns...")
        
        phone_stats = defaultdict(list)
        
        for call in calls:
            phone = call.get('callerPhone', '')
            if phone:
                phone_stats[phone].append(call)
        
        logger.info(f"Found {len(phone_stats)} unique phone numbers today")
        
        # Get thresholds from config
        analysis_config = self.config['analysis']
        frequent_threshold = analysis_config['frequent_customer_threshold']
        regular_threshold = analysis_config['regular_customer_threshold']
        
        # Daily customer segmentation with configurable thresholds
        segments = {
            'frequent': [],    # frequent_threshold+ calls today
            'regular': [],     # regular_threshold to (frequent_threshold-1) calls today  
            'single': []       # 1 call today
        }
        
        for phone, customer_calls in phone_stats.items():
            call_count = len(customer_calls)
            customer_data = {
                'phone': phone,
                'total_calls': call_count
            }
            
            if call_count >= frequent_threshold:
                segments['frequent'].append(customer_data)
            elif call_count >= regular_threshold:
                segments['regular'].append(customer_data)
            else:
                segments['single'].append(customer_data)
        
        logger.info(f"Daily customer segmentation: {len(segments['frequent'])} frequent ({frequent_threshold}+), {len(segments['regular'])} regular ({regular_threshold}+), {len(segments['single'])} single")
        
        return {
            'total_customers': len(phone_stats),
            'customer_segments': {
                'frequent_customers': len(segments['frequent']),
                'regular_customers': len(segments['regular']),
                'single_customers': len(segments['single'])
            },
            'top_customers': sorted(phone_stats.items(), key=lambda x: len(x[1]), reverse=True)[:10],
            'customer_loyalty': len(segments['frequent']) / max(1, len(phone_stats)) * 100,
            'thresholds': {
                'frequent': frequent_threshold,
                'regular': regular_threshold
            }
        }

    def _estimate_revenue_metrics(self, calls: List[Dict]) -> Dict:
        """Estimate daily revenue metrics with day/night rates and route factor."""
        logger.info("Estimating daily revenue metrics with day/night rates and route correction...")
        
        # Calculate distances for revenue estimation
        day_trips = []
        night_trips = []
        
        for call in calls:
            pickup_lat = call.get('latitude')
            pickup_lng = call.get('longitude')
            dest_lat = call.get('destLatitude')
            dest_lng = call.get('destLongitude')
            
            if all([pickup_lat, pickup_lng, dest_lat, dest_lng]) and all([coord != 0 for coord in [pickup_lat, pickup_lng, dest_lat, dest_lng]]):
                distance = self.haversine_distance(pickup_lat, pickup_lng, dest_lat, dest_lng)
                analysis_config = self.config['analysis']
                if analysis_config['min_distance_km'] <= distance <= analysis_config['max_distance_km']:
                    # Determine if this is a day or night trip
                    if self._is_night_time(call['logTimestamp']):
                        night_trips.append({'distance': distance, 'call': call})
                    else:
                        day_trips.append({'distance': distance, 'call': call})
        
        logger.info(f"Calculated distances for {len(day_trips)} day trips and {len(night_trips)} night trips")
        
        if not day_trips and not night_trips:
            logger.warning("No valid distances found for revenue estimation")
            return {'estimated_revenue': 0, 'avg_fare': 0}
        
        # Get taxi rates from config
        taxi_rates = self.config['taxi_rates']
        base_fare_day = taxi_rates['base_fare_day']
        per_km_rate_day = taxi_rates['per_km_rate_day']
        base_fare_night = taxi_rates['base_fare_night']
        per_km_rate_night = taxi_rates['per_km_rate_night']
        minimum_fare = taxi_rates['minimum_fare']
        route_factor = taxi_rates['route_factor']
        
        # Calculate day fares
        day_fares = []
        day_total_distance = 0
        for trip in day_trips:
            distance = trip['distance']
            actual_distance = distance * route_factor
            total_fare = base_fare_day + (actual_distance * per_km_rate_day)
            total_fare = max(total_fare, minimum_fare)
            day_fares.append(total_fare)
            day_total_distance += distance
        
        # Calculate night fares
        night_fares = []
        night_total_distance = 0
        for trip in night_trips:
            distance = trip['distance']
            actual_distance = distance * route_factor
            total_fare = base_fare_night + (actual_distance * per_km_rate_night)
            total_fare = max(total_fare, minimum_fare)
            night_fares.append(total_fare)
            night_total_distance += distance
        
        # Calculate totals
        total_day_revenue = sum(day_fares)
        total_night_revenue = sum(night_fares)
        total_estimated_revenue = total_day_revenue + total_night_revenue
        all_distances = [trip['distance'] for trip in day_trips + night_trips]
        all_fares = day_fares + night_fares
        
        logger.info(f"Daily revenue estimation complete:")
        logger.info(f"  Day trips: {len(day_trips)} trips, {total_day_revenue:.2f}€ revenue")
        logger.info(f"  Night trips: {len(night_trips)} trips, {total_night_revenue:.2f}€ revenue")
        logger.info(f"  Total: {total_estimated_revenue:.2f}€ with route factor")
        
        return {
            'trips_with_distance': len(day_trips) + len(night_trips),
            'day_trips': len(day_trips),
            'night_trips': len(night_trips),
            'total_distance_km': sum(all_distances),
            'day_distance_km': day_total_distance,
            'night_distance_km': night_total_distance,
            'actual_distance_km': sum(all_distances) * route_factor,
            'avg_trip_distance': statistics.mean(all_distances) if all_distances else 0,
            'estimated_total_revenue': total_estimated_revenue,
            'day_revenue': total_day_revenue,
            'night_revenue': total_night_revenue,
            'avg_fare': statistics.mean(all_fares) if all_fares else 0,
            'avg_day_fare': statistics.mean(day_fares) if day_fares else 0,
            'avg_night_fare': statistics.mean(night_fares) if night_fares else 0,
            'route_factor': route_factor,
            'base_fare_day': base_fare_day,
            'per_km_rate_day': per_km_rate_day,
            'base_fare_night': base_fare_night,
            'per_km_rate_night': per_km_rate_night,
            'minimum_fare': minimum_fare,
            'night_hours': f"{taxi_rates['night_hours_start']:02d}:00-{taxi_rates['night_hours_end']:02d}:00"
        }

    def generate_premium_html_report(self, file_path: str) -> str:
        """Generate a professional HTML report with Greek interface and yellow/black theme for daily data."""
        if not self.calls:
            return self._generate_error_report("Δεν υπάρχουν διαθέσιμα δεδομένα")
        
        logger.info("Starting comprehensive daily analysis...")
        
        # Perform comprehensive analysis
        basic_stats = self._analyze_basic_stats()
        logger.info("Basic stats analysis complete")
        
        time_patterns = self._analyze_time_patterns()
        logger.info("Time patterns analysis complete")
        
        customer_analysis = self._analyze_customers()
        logger.info("Customer analysis complete")
        
        location_analysis = self._analyze_locations()
        logger.info("Location analysis complete")
        
        reservation_analysis = self._analyze_reservation_patterns()
        logger.info("Reservation analysis complete")
        
        advanced_metrics = self.analyze_advanced_metrics(self.calls)
        logger.info("Advanced metrics analysis complete")
        
        # Generate report sections
        logger.info("Generating report sections...")
        header_section = self._generate_header_section(file_path)
        main_cards_section = self._generate_main_cards_section(basic_stats, advanced_metrics)
        details_sections = self._generate_details_sections(basic_stats, reservation_analysis, customer_analysis, location_analysis, advanced_metrics)
        
        logger.info("All sections generated, combining HTML...")
        
        # Combine all sections
        company_config = self.config['company']
        title = company_config['title']
        
        html_content = f"""
        <!DOCTYPE html>
        <html lang="el">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>{title}</title>
            {self._generate_styles()}
        </head>
        <body>
            <div id="app">
                {header_section}
                {main_cards_section}
                {details_sections}
                {self._generate_footer()}
            </div>
            
            {self._generate_revenue_info_dialog(advanced_metrics)}
            
            {self._generate_scripts()}
        </body>
        </html>
        """
        
        logger.info("HTML content generated successfully")
        return html_content

    def _generate_revenue_info_dialog(self, advanced_metrics: Dict) -> str:
        """Generate the revenue info dialog with day/night rates using actual config values."""
        # Get taxi rates directly from config to ensure correct values
        taxi_rates = self.config['taxi_rates']
        
        # Calculate route correction percentage
        route_factor = taxi_rates['route_factor']
        route_correction_percent = int((route_factor - 1) * 100)
        
        # Create night hours string
        night_hours = f"{taxi_rates['night_hours_start']:02d}:00-{taxi_rates['night_hours_end']:02d}:00"
        
        # Create explanation based on route factor
        if route_correction_percent == 0:
            route_explanation = "Δεν εφαρμόζεται διόρθωση - χρησιμοποιούνται οι ευθείες αποστάσεις."
        elif route_correction_percent > 0:
            route_explanation = f"Τα ταξί δεν ακολουθούν ευθείες γραμμές αλλά δρόμους, στροφές, φανάρια και κίνηση. Στην πόλη, η πραγματική απόσταση είναι συνήθως {route_correction_percent}% μεγαλύτερη από την ευθεία."
        else:
            route_explanation = f"Εφαρμόζεται μείωση {abs(route_correction_percent)}% στην απόσταση."
        
        return f"""
        <!-- Revenue Info Dialog -->
        <div id="revenue-info-dialog" class="info-dialog">
            <div class="info-dialog-content">
                <h3>💰 Μεθοδολογία Υπολογισμού Εσόδων (Ημερήσια/Νυχτερινά Τιμολόγια)</h3>
                <p><strong>Προσοχή:</strong> Αυτοί οι υπολογισμοί είναι εκτιμήσεις βασισμένες σε ευθείες γραμμές.</p>
                
                <div class="calculation-steps">
                    <p><strong>Βήματα Υπολογισμού:</strong></p>
                    <p>1️⃣ <strong>Ευθεία Απόσταση:</strong> Χρήση Haversine formula</p>
                    <p>2️⃣ <strong>Διόρθωση Διαδρομής:</strong> {route_correction_percent:+d}% για πραγματικούς δρόμους</p>
                    <p>3️⃣ <strong>Ωράριο:</strong> {night_hours} = νυχτερινό τιμολόγιο</p>
                </div>
                
                <div class="rate-comparison">
                    <div class="rate-section">
                        <h4>🌞 Ημερήσιο Τιμολόγιο</h4>
                        <p><strong>Βασικό Ναύλο:</strong> {taxi_rates['base_fare_day']:.2f}€</p>
                        <p><strong>Τιμή ανά km:</strong> {taxi_rates['per_km_rate_day']:.2f}€</p>
                    </div>
                    
                    <div class="rate-section">
                        <h4>🌙 Νυχτερινό Τιμολόγιο</h4>
                        <p><strong>Βασικό Ναύλο:</strong> {taxi_rates['base_fare_night']:.2f}€</p>
                        <p><strong>Τιμή ανά km:</strong> {taxi_rates['per_km_rate_night']:.2f}€</p>
                    </div>
                </div>
                
                <p><strong>Κοινά για όλες τις διαδρομές:</strong></p>
                <p>• <strong>Ελάχιστο Ναύλο:</strong> {taxi_rates['minimum_fare']:.2f}€</p>
                <p>• <strong>Συντελεστής Διαδρομής:</strong> {route_factor:.2f} ({route_factor:.0%})</p>
                
                <p><strong>Γιατί {route_correction_percent:+d}% διόρθωση;</strong></p>
                <p>{route_explanation}</p>
                
                <p><strong>Τύπος:</strong> Τελικό Κόστος = max(Βασικό Ναύλο + (Απόσταση × Συντελεστής × Τιμή/km), Ελάχιστο Ναύλο)</p>
                
                <button class="close-button" onclick="closeRevenueInfo()">Κλείσιμο</button>
            </div>
        </div>
        """

    def _generate_revenue_analysis_card(self, revenue_insights: Dict) -> str:
        """Generate detailed revenue analysis card with day/night breakdown using correct config values."""
        day_trips = revenue_insights.get('day_trips', 0)
        night_trips = revenue_insights.get('night_trips', 0)
        total_trips = day_trips + night_trips
        
        day_revenue = revenue_insights.get('day_revenue', 0)
        night_revenue = revenue_insights.get('night_revenue', 0)
        total_revenue = revenue_insights.get('estimated_total_revenue', 0)
        
        # Calculate percentages
        day_trip_pct = (day_trips / total_trips * 100) if total_trips > 0 else 0
        night_trip_pct = (night_trips / total_trips * 100) if total_trips > 0 else 0
        day_revenue_pct = (day_revenue / total_revenue * 100) if total_revenue > 0 else 0
        night_revenue_pct = (night_revenue / total_revenue * 100) if total_revenue > 0 else 0
        
        # Get correct values from config instead of revenue_insights
        taxi_rates = self.config['taxi_rates']
        night_start = taxi_rates['night_hours_start']
        night_end = taxi_rates['night_hours_end']
        route_factor = taxi_rates['route_factor']
        
        # Calculate day hours (inverse of night hours)
        day_hours = f"{night_end:02d}:00-{night_start:02d}:00"
        night_hours = f"{night_start:02d}:00-{night_end:02d}:00"
        
        # Calculate route correction percentage
        route_correction_percent = int((route_factor - 1) * 100)
        
        return f"""
        <div class="info-card">
            <h3>💰 Ανάλυση Εσόδων (Ημερήσια/Νυχτερινά Τιμολόγια)</h3>
            
            <div class="revenue-breakdown">
                <div class="breakdown-section">
                    <h4>🌞 Ημερήσιες Διαδρομές ({day_hours})</h4>
                    <div class="list-item">
                        <span class="list-text">Διαδρομές</span>
                        <span class="list-count">{day_trips} ({day_trip_pct:.1f}%)</span>
                    </div>
                    <div class="list-item">
                        <span class="list-text">Έσοδα</span>
                        <span class="list-count">{day_revenue:.2f}€ ({day_revenue_pct:.1f}%)</span>
                    </div>
                    <div class="list-item">
                        <span class="list-text">Μέση Τιμή</span>
                        <span class="list-count">{revenue_insights.get('avg_day_fare', 0):.2f}€</span>
                    </div>
                    <div class="list-item">
                        <span class="list-text">Απόσταση</span>
                        <span class="list-count">{revenue_insights.get('day_distance_km', 0):.1f} km</span>
                    </div>
                </div>
                
                <div class="breakdown-section">
                    <h4>🌙 Νυχτερινές Διαδρομές ({night_hours})</h4>
                    <div class="list-item">
                        <span class="list-text">Διαδρομές</span>
                        <span class="list-count">{night_trips} ({night_trip_pct:.1f}%)</span>
                    </div>
                    <div class="list-item">
                        <span class="list-text">Έσοδα</span>
                        <span class="list-count">{night_revenue:.2f}€ ({night_revenue_pct:.1f}%)</span>
                    </div>
                    <div class="list-item">
                        <span class="list-text">Μέση Τιμή</span>
                        <span class="list-count">{revenue_insights.get('avg_night_fare', 0):.2f}€</span>
                    </div>
                    <div class="list-item">
                        <span class="list-text">Απόσταση</span>
                        <span class="list-count">{revenue_insights.get('night_distance_km', 0):.1f} km</span>
                    </div>
                </div>
            </div>
            
            <div class="total-section">
                <div class="list-item">
                    <span class="list-text"><strong>Συνολικά Έσοδα</strong></span>
                    <span class="list-count"><strong>{total_revenue:.2f}€</strong></span>
                </div>
                <div class="list-item">
                    <span class="list-text">Διαδρομές με Απόσταση</span>
                    <span class="list-count">{revenue_insights.get('trips_with_distance', 0)}</span>
                </div>
                <div class="list-item">
                    <span class="list-text">Μέση Τιμή Διαδρομής</span>
                    <span class="list-count">{revenue_insights.get('avg_fare', 0):.2f}€</span>
                </div>
                <div class="list-item">
                    <span class="list-text">Συνολική Απόσταση (ευθεία)</span>
                    <span class="list-count">{revenue_insights.get('total_distance_km', 0):.1f} km</span>
                </div>
                <div class="list-item">
                    <span class="list-text">Πραγματική Απόσταση ({route_correction_percent:+d}%)</span>
                    <span class="list-count">{revenue_insights.get('actual_distance_km', 0):.1f} km</span>
                </div>
            </div>
        </div>
        """

    def _generate_styles(self) -> str:
        """Generate Greek-themed yellow/black CSS styles."""
        return """
        <style>
            :root {
                --primary-yellow: #FFD700;
                --dark-yellow: #FFA500;
                --light-yellow: #FFFFE0;
                --black: #000000;
                --dark-gray: #1a1a1a;
                --light-gray: #333333;
                --white: #FFFFFF;
                --border-radius: 15px;
                --shadow: 0 8px 32px rgba(255, 215, 0, 0.3);
                --glow: 0 0 20px rgba(255, 215, 0, 0.5);
            }
            
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                line-height: 1.6;
                color: var(--white);
                background: linear-gradient(135deg, var(--black) 0%, var(--dark-gray) 50%, var(--light-gray) 100%);
                min-height: 100vh;
                overflow-x: hidden;
            }
            
            /* Animated Background */
            body::before {
                content: '';
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: 
                    radial-gradient(circle at 20% 20%, rgba(255, 215, 0, 0.1) 0%, transparent 50%),
                    radial-gradient(circle at 80% 80%, rgba(255, 165, 0, 0.1) 0%, transparent 50%);
                animation: backgroundMove 20s ease-in-out infinite;
                pointer-events: none;
                z-index: -1;
            }
            
            @keyframes backgroundMove {
                0%, 100% { transform: translateX(0) translateY(0); }
                50% { transform: translateX(-20px) translateY(-20px); }
            }
            
            #app {
                position: relative;
                z-index: 1;
                padding: 20px;
                max-width: 1400px;
                margin: 0 auto;
            }
            
            /* Header */
            .header {
                background: linear-gradient(135deg, var(--black) 0%, var(--dark-gray) 100%);
                border: 3px solid var(--primary-yellow);
                border-radius: var(--border-radius);
                padding: 40px;
                margin-bottom: 30px;
                text-align: center;
                position: relative;
                overflow: hidden;
                box-shadow: var(--shadow);
            }
            
            .header::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 3px;
                background: linear-gradient(90deg, var(--primary-yellow), var(--dark-yellow), var(--primary-yellow));
                animation: borderGlow 2s infinite;
            }
            
            @keyframes borderGlow {
                0%, 100% { opacity: 1; }
                50% { opacity: 0.5; }
            }
            
            .header h1 {
                font-size: 3em;
                font-weight: 800;
                margin-bottom: 15px;
                color: var(--primary-yellow);
                text-shadow: var(--glow);
                animation: titleGlow 3s ease-in-out infinite alternate;
            }
            
            @keyframes titleGlow {
                from { text-shadow: 0 0 20px rgba(255, 215, 0, 0.5); }
                to { text-shadow: 0 0 30px rgba(255, 215, 0, 0.8); }
            }
            
            .header .subtitle {
                font-size: 1.2em;
                color: var(--white);
                margin-bottom: 10px;
            }
            
            .header .timestamp {
                font-size: 1em;
                color: var(--primary-yellow);
                opacity: 0.8;
            }
            
            /* Print Button */
            .header-buttons {
                margin-top: 20px;
                display: flex;
                gap: 15px;
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .print-button, .info-button {
                background: linear-gradient(135deg, var(--primary-yellow) 0%, var(--dark-yellow) 100%);
                color: var(--black);
                border: none;
                padding: 12px 25px;
                border-radius: 25px;
                font-size: 1.1em;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                box-shadow: 0 4px 15px rgba(255, 215, 0, 0.4);
            }
            
            .print-button:hover, .info-button:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(255, 215, 0, 0.6);
                background: linear-gradient(135deg, var(--dark-yellow) 0%, var(--primary-yellow) 100%);
            }
            
            /* Info Dialog */
            .info-dialog {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.8);
                z-index: 1000;
                backdrop-filter: blur(5px);
            }
            
            .info-dialog-content {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: linear-gradient(135deg, var(--dark-gray) 0%, var(--black) 100%);
                border: 2px solid var(--primary-yellow);
                border-radius: var(--border-radius);
                padding: 30px;
                max-width: 600px;
                width: 90%;
                color: var(--white);
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
                max-height: 80vh;
                overflow-y: auto;
            }
            
            .info-dialog h3 {
                color: var(--primary-yellow);
                font-size: 1.5em;
                margin-bottom: 20px;
                text-align: center;
            }
            
            .info-dialog p {
                margin-bottom: 15px;
                line-height: 1.6;
            }
            
            .info-dialog .calculation-steps {
                background: rgba(255, 215, 0, 0.1);
                border-radius: 8px;
                padding: 15px;
                margin: 15px 0;
                border: 1px solid rgba(255, 215, 0, 0.3);
            }
            
            .info-dialog .close-button {
                background: var(--primary-yellow);
                color: var(--black);
                border: none;
                padding: 10px 20px;
                border-radius: 20px;
                font-weight: 600;
                cursor: pointer;
                display: block;
                margin: 20px auto 0;
                transition: all 0.3s ease;
            }
            
            .info-dialog .close-button:hover {
                background: var(--dark-yellow);
                transform: translateY(-2px);
            }
            
            /* Revenue Breakdown Styles */
            .revenue-breakdown {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 15px;
                margin: 15px 0;
            }
            
            .breakdown-section {
                background: rgba(255, 215, 0, 0.05);
                border-radius: 8px;
                padding: 15px;
                border: 1px solid rgba(255, 215, 0, 0.2);
            }
            
            .breakdown-section h4 {
                color: var(--primary-yellow);
                font-size: 1.1em;
                margin-bottom: 12px;
                font-weight: 600;
                border-bottom: 1px solid rgba(255, 215, 0, 0.3);
                padding-bottom: 5px;
            }
            
            .total-section {
                margin-top: 20px;
                padding-top: 15px;
                border-top: 2px solid var(--primary-yellow);
                background: rgba(255, 215, 0, 0.1);
                border-radius: 8px;
                padding: 15px;
            }
            
            .rate-comparison {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 15px;
                margin: 15px 0;
            }
            
            .rate-section {
                background: rgba(255, 215, 0, 0.05);
                border-radius: 8px;
                padding: 15px;
                border: 1px solid rgba(255, 215, 0, 0.2);
            }
            
            .rate-section h4 {
                color: var(--primary-yellow);
                font-size: 1.1em;
                margin-bottom: 10px;
                font-weight: 600;
            }
            
            .rate-section p {
                margin-bottom: 8px;
                font-size: 0.95em;
            }
            
            /* Main Cards Grid */
            .main-cards-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 25px;
                margin-bottom: 50px;
            }
            
            .main-card {
                background: linear-gradient(135deg, var(--dark-gray) 0%, var(--black) 100%);
                border: 2px solid var(--primary-yellow);
                border-radius: var(--border-radius);
                padding: 30px;
                text-align: center;
                position: relative;
                overflow: hidden;
                transition: all 0.3s ease;
                cursor: pointer;
                box-shadow: var(--shadow);
            }
            
            .main-card::before {
                content: '';
                position: absolute;
                top: 0;
                left: -100%;
                width: 100%;
                height: 100%;
                background: linear-gradient(90deg, transparent, rgba(255, 215, 0, 0.1), transparent);
                transition: left 0.5s;
            }
            
            .main-card:hover::before {
                left: 100%;
            }
            
            .main-card:hover {
                transform: translateY(-10px) scale(1.02);
                border-color: var(--dark-yellow);
                box-shadow: 0 12px 40px rgba(255, 215, 0, 0.4);
            }
            
            .main-card-icon {
                font-size: 3em;
                margin-bottom: 15px;
                color: var(--primary-yellow);
                animation: bounce 2s infinite;
            }
            
            @keyframes bounce {
                0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
                40% { transform: translateY(-10px); }
                60% { transform: translateY(-5px); }
            }
            
            .main-card-number {
                font-size: 2.5em;
                font-weight: 900;
                margin-bottom: 10px;
                color: var(--primary-yellow);
                text-shadow: var(--glow);
            }
            
            .main-card-label {
                font-size: 1.2em;
                color: var(--white);
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            
            /* Dashboard Card Special Styling */
            .dashboard-card {
                background: linear-gradient(135deg, var(--black) 0%, var(--dark-gray) 100%);
                border: 2px solid var(--dark-yellow);
            }
            
            .dashboard-metrics {
                display: flex;
                justify-content: space-around;
                margin-top: 20px;
                flex-wrap: wrap;
            }
            
            .dashboard-metric {
                text-align: center;
                margin: 10px 5px;
            }
            
            .dashboard-metric-number {
                font-size: 1.8em;
                font-weight: bold;
                color: var(--primary-yellow);
                display: block;
            }
            
            .dashboard-metric-label {
                font-size: 0.9em;
                color: var(--white);
                opacity: 0.8;
            }
            
            /* Details Sections */
            .details-section {
                background: linear-gradient(135deg, var(--dark-gray) 0%, var(--black) 100%);
                border: 2px solid var(--primary-yellow);
                border-radius: var(--border-radius);
                padding: 35px;
                margin-bottom: 30px;
                position: relative;
                overflow: hidden;
                box-shadow: var(--shadow);
                animation: slideInUp 0.8s ease-out;
            }
            
            @keyframes slideInUp {
                from { opacity: 0; transform: translateY(30px); }
                to { opacity: 1; transform: translateY(0); }
            }
            
            .section-title {
                font-size: 2em;
                font-weight: 700;
                color: var(--primary-yellow);
                margin-bottom: 25px;
                display: flex;
                align-items: center;
                gap: 15px;
                text-shadow: var(--glow);
            }
            
            .section-title::after {
                content: '';
                flex: 1;
                height: 2px;
                background: linear-gradient(90deg, var(--primary-yellow), transparent);
                margin-left: 20px;
            }
            
            /* Info Grid */
            .info-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
                gap: 25px;
                margin-top: 25px;
            }
            
            .info-card {
                background: linear-gradient(135deg, var(--light-gray) 0%, var(--dark-gray) 100%);
                border: 1px solid var(--primary-yellow);
                border-radius: var(--border-radius);
                padding: 25px;
                transition: all 0.3s ease;
            }
            
            .info-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 8px 25px rgba(255, 215, 0, 0.3);
            }
            
            .info-card h3 {
                color: var(--primary-yellow);
                font-size: 1.4em;
                margin-bottom: 20px;
                font-weight: 600;
                border-bottom: 2px solid var(--primary-yellow);
                padding-bottom: 10px;
            }
            
            .list-item {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 12px 0;
                border-bottom: 1px solid rgba(255, 215, 0, 0.2);
                transition: all 0.3s ease;
            }
            
            .list-item:hover {
                background: rgba(255, 215, 0, 0.1);
                padding-left: 10px;
                border-radius: 8px;
            }
            
            .list-item:last-child {
                border-bottom: none;
            }
            
            .list-text {
                flex: 1;
                color: var(--white);
                font-size: 0.95em;
            }
            
            .list-count {
                background: linear-gradient(135deg, var(--primary-yellow) 0%, var(--dark-yellow) 100%);
                color: var(--black);
                padding: 6px 12px;
                border-radius: 20px;
                font-weight: 700;
                font-size: 0.85em;
                min-width: 40px;
                text-align: center;
                box-shadow: 0 4px 15px rgba(255, 215, 0, 0.4);
            }
            
            /* Chart Containers */
            .chart-container {
                background: rgba(255, 215, 0, 0.1);
                border-radius: var(--border-radius);
                padding: 25px;
                margin: 20px 0;
                border: 1px solid rgba(255, 215, 0, 0.3);
            }
            
            /* Dual Bar Chart Styles */
            .chart-container-dual {
                background: rgba(255, 215, 0, 0.1);
                border-radius: var(--border-radius);
                padding: 25px;
                margin: 20px 0;
                border: 1px solid rgba(255, 215, 0, 0.3);
            }
            
            .chart-legend-dual {
                display: flex;
                justify-content: center;
                gap: 30px;
                margin-bottom: 20px;
                flex-wrap: wrap;
            }
            
            .legend-item-dual {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 0.9em;
                color: var(--white);
                font-weight: 600;
            }
            
            .legend-color {
                width: 20px;
                height: 12px;
                border-radius: 6px;
            }
            
            .reservation-color {
                background: linear-gradient(90deg, #ffc107 0%, #ffeb3b 100%);
            }
            
            .immediate-color {
                background: linear-gradient(90deg, #2196f3 0%, #03a9f4 100%);
            }
            
            .chart-row-dual {
                display: flex;
                align-items: center;
                margin-bottom: 15px;
                padding: 8px 0;
                border-radius: 8px;
                transition: all 0.3s ease;
            }
            
            .chart-row-dual:hover {
                background: rgba(255, 215, 0, 0.1);
                transform: translateX(5px);
            }
            
            .chart-label-dual {
                width: 60px;
                font-weight: 600;
                color: var(--white);
                font-size: 0.95em;
            }
            
            .chart-bars-container {
                flex: 1;
                display: flex;
                flex-direction: column;
                gap: 3px;
                margin-left: 15px;
            }
            
            .chart-bar-dual {
                height: 20px;
                background: rgba(255, 255, 255, 0.1);
                border-radius: 10px;
                position: relative;
                overflow: hidden;
                border: 1px solid rgba(255, 255, 255, 0.2);
            }
            
            .reservation-bar {
                background: rgba(255, 193, 7, 0.2);
                border: 1px solid rgba(255, 193, 7, 0.3);
            }
            
            .immediate-bar {
                background: rgba(33, 150, 243, 0.2);
                border: 1px solid rgba(33, 150, 243, 0.3);
            }
            
            .reservation-fill {
                height: 100%;
                background: linear-gradient(90deg, #ffc107 0%, #ffeb3b 100%);
                border-radius: 10px;
                transition: width 1.5s cubic-bezier(0.4, 0.0, 0.2, 1);
                position: relative;
                box-shadow: 0 0 15px rgba(255, 193, 7, 0.5);
            }
            
            .immediate-fill {
                height: 100%;
                background: linear-gradient(90deg, #2196f3 0%, #03a9f4 100%);
                border-radius: 10px;
                transition: width 1.5s cubic-bezier(0.4, 0.0, 0.2, 1);
                position: relative;
                box-shadow: 0 0 15px rgba(33, 150, 243, 0.5);
            }
            
            .chart-value-dual {
                position: absolute;
                right: 8px;
                top: 50%;
                transform: translateY(-50%);
                font-weight: 700;
                font-size: 0.75em;
                text-shadow: 0 0 5px rgba(0, 0, 0, 0.7);
            }
            
            .reservation-text {
                color: #333333;
            }
            
            .immediate-text {
                color: white;
            }
            
            .chart-total {
                width: 50px;
                text-align: center;
                font-weight: 700;
                color: var(--primary-yellow);
                font-size: 1.1em;
                margin-left: 15px;
            }
            
            .chart-row {
                display: flex;
                align-items: center;
                margin-bottom: 12px;
                padding: 8px 0;
                border-radius: 8px;
                transition: all 0.3s ease;
            }
            
            .chart-row:hover {
                background: rgba(255, 215, 0, 0.1);
                transform: translateX(5px);
            }
            
            .chart-label {
                width: 80px;
                font-weight: 600;
                color: var(--white);
                font-size: 0.95em;
            }
            
            .chart-bar {
                flex: 1;
                height: 30px;
                background: rgba(255, 215, 0, 0.2);
                border-radius: 15px;
                position: relative;
                margin-left: 15px;
                overflow: hidden;
            }
            
            .chart-fill {
                height: 100%;
                background: linear-gradient(90deg, var(--primary-yellow) 0%, var(--dark-yellow) 100%);
                border-radius: 15px;
                transition: width 1.5s cubic-bezier(0.4, 0.0, 0.2, 1);
                position: relative;
                box-shadow: var(--glow);
            }
            
            .chart-value {
                position: absolute;
                right: 15px;
                top: 50%;
                transform: translateY(-50%);
                font-weight: 700;
                color: var(--black);
                font-size: 0.9em;
            }
            
            /* Footer */
            .footer {
                text-align: center;
                padding: 30px;
                background: linear-gradient(135deg, var(--black) 0%, var(--dark-gray) 100%);
                border: 2px solid var(--primary-yellow);
                border-radius: var(--border-radius);
                margin-top: 40px;
                color: var(--white);
            }
            
            /* Responsive Design */
            @media (max-width: 768px) {
                .main-cards-grid {
                    grid-template-columns: 1fr;
                }
                
                .info-grid {
                    grid-template-columns: 1fr;
                }
                
                .revenue-breakdown {
                    grid-template-columns: 1fr;
                }
                
                .rate-comparison {
                    grid-template-columns: 1fr;
                }
                
                .header h1 {
                    font-size: 2em;
                }
                
                #app {
                    padding: 15px;
                }
                
                .dashboard-metrics {
                    flex-direction: column;
                }
            }
            
            /* Scroll to section animation */
            html {
                scroll-behavior: smooth;
            }
            
            /* Loading states */
            .animate-on-scroll {
                opacity: 0;
                transform: translateY(30px);
                transition: all 0.6s ease;
            }
            
            .animate-on-scroll.animated {
                opacity: 1;
                transform: translateY(0);
            }
        </style>
        """

    def _generate_header_section(self, file_path: str) -> str:
        """Generate the header section in Greek for daily report using config values."""
        current_time = datetime.now().strftime('%d %B %Y στις %H:%M')
        current_date = datetime.now().strftime('%d %B %Y')
        
        # Get values from config
        company_config = self.config['company']
        title = company_config['title']
        subtitle = company_config['subtitle']
        
        return f"""
        <div class="header">
            <h1>{title}</h1>
            <div class="subtitle">{subtitle}</div>
            <div class="timestamp">Αναφορά για {current_date}<br>📊 Πηγή: {os.path.basename(file_path)}</div>
            <div class="header-buttons">
                <button class="print-button" onclick="generatePrintVersion()">
                    🖨️ Εκτύπωση Αναφοράς
                </button>
                <button class="info-button" onclick="showRevenueInfo()">
                    ℹ️ Πληροφορίες Εσόδων
                </button>
            </div>
        </div>
        """

    def _generate_main_cards_section(self, basic_stats: Dict, advanced_metrics: Dict) -> str:
        """Generate the main 4 cards section."""
        # Get revenue and customer stats
        revenue = advanced_metrics.get('revenue_insights', {}).get('estimated_total_revenue', 0)
        unique_customers = basic_stats.get('unique_customers', 0)
        repeat_customers = basic_stats.get('repeat_customers', 0)
        
        return f"""
        <div class="main-cards-grid">
            <div class="main-card" onclick="scrollToSection('total-calls-section')">
                <div class="main-card-icon">📞</div>
                <div class="main-card-number">{basic_stats.get('total_calls', 0)}</div>
                <div class="main-card-label">Σύνολο Κλήσεων Σήμερα</div>
            </div>
            
            <div class="main-card" onclick="scrollToSection('immediate-calls-section')">
                <div class="main-card-icon">⚡</div>
                <div class="main-card-number">{basic_stats.get('immediate_calls', 0)}</div>
                <div class="main-card-label">Άμεσες Κλήσεις</div>
            </div>
            
            <div class="main-card" onclick="scrollToSection('reservations-section')">
                <div class="main-card-icon">📅</div>
                <div class="main-card-number">{basic_stats.get('reservations', 0)}</div>
                <div class="main-card-label">Κρατήσεις</div>
            </div>
            
            <div class="main-card dashboard-card" onclick="scrollToSection('dashboard-section')">
                <div class="main-card-icon">📊</div>
                <div class="main-card-label">Ημερήσιο Dashboard</div>
                <div class="dashboard-metrics">
                    <div class="dashboard-metric">
                        <span class="dashboard-metric-number">{unique_customers}</span>
                        <span class="dashboard-metric-label">Μοναδικοί Πελάτες</span>
                    </div>
                    <div class="dashboard-metric">
                        <span class="dashboard-metric-number">{repeat_customers}</span>
                        <span class="dashboard-metric-label">Επαναλαμβάνοντες</span>
                    </div>
                    <div class="dashboard-metric">
                        <span class="dashboard-metric-number">{revenue:.0f}€</span>
                        <span class="dashboard-metric-label">Έσοδα Σήμερα</span>
                    </div>
                </div>
            </div>
        </div>
        """

    def _generate_details_sections(self, basic_stats: Dict, reservation_analysis: Dict, customer_analysis: Dict, location_analysis: Dict, advanced_metrics: Dict) -> str:
        """Generate all detail sections with properly separated data."""
        
        # Total Calls Section with dual-bar hourly chart
        time_patterns = advanced_metrics.get('time_analysis', {})
        reservation_hours = reservation_analysis.get('reservation_hours', {})
        immediate_hours = reservation_analysis.get('immediate_hours', {})
        
        # Calculate totals for percentages
        total_reservations = sum(reservation_hours.values())
        total_immediate = sum(immediate_hours.values())
        total_calls = total_reservations + total_immediate
        
        # Find max calls for chart scaling
        max_calls_hour = 0
        for hour in range(24):
            hour_total = reservation_hours.get(hour, 0) + immediate_hours.get(hour, 0)
            max_calls_hour = max(max_calls_hour, hour_total)
        
        if max_calls_hour == 0:
            max_calls_hour = 1  # Prevent division by zero
        
        # Generate dual-bar hourly chart
        hourly_chart = ""
        for hour in range(24):
            res_count = reservation_hours.get(hour, 0)
            imm_count = immediate_hours.get(hour, 0)
            hour_total = res_count + imm_count
            
            # Calculate percentages
            res_percentage_of_hour = (res_count / hour_total * 100) if hour_total > 0 else 0
            imm_percentage_of_hour = (imm_count / hour_total * 100) if hour_total > 0 else 0
            
            # Calculate bar widths for visualization
            res_bar_width = (res_count / max_calls_hour * 100) if max_calls_hour > 0 else 0
            imm_bar_width = (imm_count / max_calls_hour * 100) if max_calls_hour > 0 else 0
            
            hourly_chart += f"""
            <div class="chart-row-dual">
                <div class="chart-label-dual">{hour:02d}:00</div>
                <div class="chart-bars-container">
                    <div class="chart-bar-dual reservation-bar">
                        <div class="chart-fill reservation-fill" style="width: {res_bar_width}%"></div>
                        <div class="chart-value-dual reservation-text">{res_count} ({res_percentage_of_hour:.0f}%)</div>
                    </div>
                    <div class="chart-bar-dual immediate-bar">
                        <div class="chart-fill immediate-fill" style="width: {imm_bar_width}%"></div>
                        <div class="chart-value-dual immediate-text">{imm_count} ({imm_percentage_of_hour:.0f}%)</div>
                    </div>
                </div>
                <div class="chart-total">{hour_total}</div>
            </div>
            """
        
        # Separate immediate customers from all customers
        immediate_customers = reservation_analysis.get('top_immediate_customers', [])[:10]
        immediate_customer_cards = ""
        if immediate_customers:
            for phone, count in immediate_customers:
                immediate_customer_cards += f"""
                <div class="list-item">
                    <span class="list-text">{phone}</span>
                    <span class="list-count">{count}</span>
                </div>
                """
        else:
            immediate_customer_cards = '<div class="list-item"><span class="list-text">Δεν υπάρχουν δεδομένα σήμερα</span></div>'
        
        # Top locations for reservations
        reservation_locations = reservation_analysis.get('top_reservation_locations', [])[:10]
        reservation_location_cards = ""
        if reservation_locations:
            for location, count in reservation_locations:
                if location and location.strip():
                    reservation_location_cards += f"""
                    <div class="list-item">
                        <span class="list-text">{location[:40]}{"..." if len(location) > 40 else ""}</span>
                        <span class="list-count">{count}</span>
                    </div>
                    """
        
        if not reservation_location_cards:
            reservation_location_cards = '<div class="list-item"><span class="list-text">Δεν υπάρχουν δεδομένα σήμερα</span></div>'
        
        # All customer cards for dashboard
        top_customers = customer_analysis.get('top_customers', [])[:10]
        customer_cards = ""
        if top_customers:
            for phone, count in top_customers:
                customer_cards += f"""
                <div class="list-item">
                    <span class="list-text">{phone}</span>
                    <span class="list-count">{count}</span>
                </div>
                """
        else:
            customer_cards = '<div class="list-item"><span class="list-text">Δεν υπάρχουν δεδομένα σήμερα</span></div>'
        
        # Revenue analysis
        revenue_insights = advanced_metrics.get('revenue_insights', {})
        
        # Calculate additional call statistics
        if self.calls:
            sorted_calls = sorted(self.calls, key=lambda x: x['logTimestamp'])
            first_call_time = sorted_calls[0]['logTimestamp'].strftime('%H:%M')
            last_call_time = sorted_calls[-1]['logTimestamp'].strftime('%H:%M')
            
            # Calculate gaps between calls
            call_gaps = []
            for i in range(1, len(sorted_calls)):
                gap = (sorted_calls[i]['logTimestamp'] - sorted_calls[i-1]['logTimestamp']).total_seconds() / 60
                call_gaps.append(gap)
            
            max_gap = max(call_gaps) if call_gaps else 0
            avg_gap = statistics.mean(call_gaps) if call_gaps else 0
            
            # Find quietest hour (hour with least calls)
            hourly_data = time_patterns.get('hourly_breakdown', {})
            if hourly_data:
                non_zero_hours = {int(h): count for h, count in hourly_data.items() if count > 0}
                quietest_hour = min(non_zero_hours.keys(), key=lambda x: non_zero_hours[x]) if non_zero_hours else 0
            else:
                quietest_hour = 0
            
            # Calculate time period distributions
            morning_calls = sum(1 for call in self.calls if 5 <= call['logTimestamp'].hour < 12)  # 05:00-11:59
            afternoon_calls = sum(1 for call in self.calls if 12 <= call['logTimestamp'].hour < 18)  # 12:00-17:59
            evening_calls = sum(1 for call in self.calls if 18 <= call['logTimestamp'].hour < 24)  # 18:00-23:59
            night_calls = sum(1 for call in self.calls if 0 <= call['logTimestamp'].hour < 5)  # 00:00-04:59
            
        else:
            first_call_time = "N/A"
            last_call_time = "N/A"
            max_gap = 0
            avg_gap = 0
            quietest_hour = 0
            morning_calls = afternoon_calls = evening_calls = night_calls = 0
        
        return f"""
        <!-- Total Calls Section -->
        <div id="total-calls-section" class="details-section animate-on-scroll">
            <h2 class="section-title">📞 Ανάλυση Συνολικών Κλήσεων Σήμερα</h2>
            <div class="info-grid">
                <div class="info-card">
                    <h3>⏰ Ωριαία Κατανομή (Κρατήσεις vs Άμεσες)</h3>
                    <div class="chart-legend-dual">
                        <div class="legend-item-dual">
                            <div class="legend-color reservation-color"></div>
                            <span>📅 Κρατήσεις ({total_reservations} - {(total_reservations/total_calls*100) if total_calls > 0 else 0:.1f}%)</span>
                        </div>
                        <div class="legend-item-dual">
                            <div class="legend-color immediate-color"></div>
                            <span>⚡ Άμεσες ({total_immediate} - {(total_immediate/total_calls*100) if total_calls > 0 else 0:.1f}%)</span>
                        </div>
                    </div>
                    <div class="chart-container-dual">
                        {hourly_chart}
                    </div>
                </div>
                
                <div class="info-card">
                    <h3>📈 Στατιστικά Κλήσεων Σήμερα</h3>
                    <div class="list-item">
                        <span class="list-text">Συνολικές Κλήσεις</span>
                        <span class="list-count">{basic_stats.get('total_calls', 0)}</span>
                    </div>
                    <div class="list-item">
                        <span class="list-text">Κλήσεις ανά Ώρα</span>
                        <span class="list-count">{time_patterns.get('calls_per_hour', 0):.1f}</span>
                    </div>
                    <div class="list-item">
                        <span class="list-text">Ενεργές Ώρες</span>
                        <span class="list-count">{time_patterns.get('total_hours', 0):.1f}h</span>
                    </div>
                    <div class="list-item">
                        <span class="list-text">Ώρα Αιχμής</span>
                        <span class="list-count">{time_patterns.get('busiest_hour', 0):02d}:00</span>
                    </div>
                    <div class="list-item">
                        <span class="list-text">Πιο Ήσυχη Ώρα</span>
                        <span class="list-count">{quietest_hour:02d}:00</span>
                    </div>
                    <div class="list-item">
                        <span class="list-text">Πρώτη Κλήση</span>
                        <span class="list-count">{first_call_time}</span>
                    </div>
                    <div class="list-item">
                        <span class="list-text">Τελευταία Κλήση</span>
                        <span class="list-count">{last_call_time}</span>
                    </div>
                    <div class="list-item">
                        <span class="list-text">Μέσο Διάστημα Κλήσεων</span>
                        <span class="list-count">{avg_gap:.1f}m</span>
                    </div>
                    <div class="list-item">
                        <span class="list-text">Μεγαλύτερο Διάστημα</span>
                        <span class="list-count">{max_gap:.0f}m</span>
                    </div>
                    <div class="list-item">
                        <span class="list-text">🌅 Πρωί (05:00-11:59)</span>
                        <span class="list-count">{morning_calls}</span>
                    </div>
                    <div class="list-item">
                        <span class="list-text">🌞 Απόγευμα (12:00-17:59)</span>
                        <span class="list-count">{afternoon_calls}</span>
                    </div>
                    <div class="list-item">
                        <span class="list-text">🌆 Βράδυ (18:00-23:59)</span>
                        <span class="list-count">{evening_calls}</span>
                    </div>
                    <div class="list-item">
                        <span class="list-text">🌙 Νύχτα (00:00-04:59)</span>
                        <span class="list-count">{night_calls}</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Immediate Calls Section -->
        <div id="immediate-calls-section" class="details-section animate-on-scroll">
            <h2 class="section-title">⚡ Άμεσες Κλήσεις Σήμερα</h2>
            <div class="info-grid">
                <div class="info-card">
                    <h3>📊 Στατιστικά Άμεσων Κλήσεων</h3>
                    <div class="list-item">
                        <span class="list-text">Σύνολο Άμεσων Κλήσεων</span>
                        <span class="list-count">{basic_stats.get('immediate_calls', 0)}</span>
                    </div>
                    <div class="list-item">
                        <span class="list-text">Ποσοστό Άμεσων</span>
                        <span class="list-count">{basic_stats.get('immediate_percentage', 0):.1f}%</span>
                    </div>
                    <div class="list-item">
                        <span class="list-text">Αιχμή Άμεσων</span>
                        <span class="list-count">{reservation_analysis.get('immediate_peak_hour', 0):02d}:00</span>
                    </div>
                </div>
                
                <div class="info-card">
                    <h3>👑 Κορυφαίοι Πελάτες Άμεσων Σήμερα</h3>
                    {immediate_customer_cards}
                </div>
            </div>
        </div>
        
        <!-- Reservations Section -->
        <div id="reservations-section" class="details-section animate-on-scroll">
            <h2 class="section-title">📅 Κρατήσεις Σήμερα</h2>
            <div class="info-grid">
                <div class="info-card">
                    <h3>📊 Στατιστικά Κρατήσεων</h3>
                    <div class="list-item">
                        <span class="list-text">Σύνολο Κρατήσεων</span>
                        <span class="list-count">{basic_stats.get('reservations', 0)}</span>
                    </div>
                    <div class="list-item">
                        <span class="list-text">Ποσοστό Κρατήσεων</span>
                        <span class="list-count">{basic_stats.get('reservation_percentage', 0):.1f}%</span>
                    </div>
                    <div class="list-item">
                        <span class="list-text">Αιχμή Κρατήσεων</span>
                        <span class="list-count">{reservation_analysis.get('reservation_peak_hour', 0):02d}:00</span>
                    </div>
                </div>
                
                <div class="info-card">
                    <h3>📍 Δημοφιλείς Τοποθεσίες Κρατήσεων</h3>
                    {reservation_location_cards}
                </div>
            </div>
        </div>
        
        <!-- Dashboard Section -->
        <div id="dashboard-section" class="details-section animate-on-scroll">
            <h2 class="section-title">📊 Ημερήσιο Γενικό Dashboard</h2>
            <div class="info-grid">
                <div class="info-card">
                    <h3>👥 Ανάλυση Πελατών Σήμερα</h3>
                    <div class="list-item">
                        <span class="list-text">Μοναδικοί Πελάτες</span>
                        <span class="list-count">{basic_stats.get('unique_customers', 0)}</span>
                    </div>
                    <div class="list-item">
                        <span class="list-text">Επαναλαμβάνοντες (2+ κλήσεις)</span>
                        <span class="list-count">{basic_stats.get('repeat_customers', 0)}</span>
                    </div>
                    <div class="list-item">
                        <span class="list-text">Συχνοί (3+ κλήσεις)</span>
                        <span class="list-count">{advanced_metrics.get('customer_insights', {}).get('customer_segments', {}).get('frequent_customers', 0)}</span>
                    </div>
                </div>
                
                {self._generate_revenue_analysis_card(revenue_insights)}
                
                <div class="info-card">
                    <h3>👑 Κορυφαίοι Πελάτες Σήμερα</h3>
                    {customer_cards}
                </div>
                
                <div class="info-card">
                    <h3>⚡ Μετρήσεις Αποδοτικότητας Σήμερα</h3>
                    <div class="list-item">
                        <span class="list-text">Μέσος Χρόνος Μεταξύ Κλήσεων</span>
                        <span class="list-count">{advanced_metrics.get('efficiency_metrics', {}).get('avg_response_time', 0):.1f}m</span>
                    </div>
                    <div class="list-item">
                        <span class="list-text">Ποσοστό Χρησιμοποίησης</span>
                        <span class="list-count">{advanced_metrics.get('efficiency_metrics', {}).get('utilization_rate', 0):.1f}%</span>
                    </div>
                    <div class="list-item">
                        <span class="list-text">Ενεργές Ώρες</span>
                        <span class="list-count">{advanced_metrics.get('efficiency_metrics', {}).get('total_active_hours', 0):.1f}h</span>
                    </div>
                </div>
            </div>
        </div>
        """

    def _generate_footer(self) -> str:
        """Generate the footer section in Greek for daily report using config values."""
        company_config = self.config['company']
        company_name = company_config['name']
        footer_text = company_config['footer_text']
        
        return f"""
        <div class="footer">
            <p>🚀 Δημιουργήθηκε από {company_name} • {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}</p>
            <p>{footer_text}</p>
        </div>
        """

    def _generate_scripts(self) -> str:
        """Generate JavaScript for animations and interactions."""
        return """
        <script>
            // Show revenue info dialog
            function showRevenueInfo() {
                document.getElementById('revenue-info-dialog').style.display = 'block';
            }
            
            // Close revenue info dialog
            function closeRevenueInfo() {
                document.getElementById('revenue-info-dialog').style.display = 'none';
            }
            
            // Close dialog when clicking outside
            document.addEventListener('click', function(event) {
                const dialog = document.getElementById('revenue-info-dialog');
                if (event.target === dialog) {
                    closeRevenueInfo();
                }
            });
            
            // Generate print-friendly version
            function generatePrintVersion() {
                // Get the current report content
                const currentContent = document.documentElement.outerHTML;
                
                // Create compact print-friendly CSS for A4
                const printCSS = `
                <style>
                    * {
                        margin: 0;
                        padding: 0;
                        box-sizing: border-box;
                    }
                    
                    body {
                        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                        line-height: 1.4;
                        color: #000000;
                        background: #ffffff;
                        padding: 15mm;
                        max-width: 210mm;
                        margin: 0 auto;
                        font-size: 11pt;
                    }
                    
                    .header {
                        background: #ffffff;
                        border: 1px solid #000000;
                        border-radius: 5px;
                        padding: 15px;
                        margin-bottom: 15px;
                        text-align: center;
                        page-break-inside: avoid;
                    }
                    
                    .header h1 {
                        font-size: 18pt;
                        font-weight: bold;
                        margin-bottom: 8px;
                        color: #000000;
                    }
                    
                    .header .subtitle {
                        font-size: 10pt;
                        color: #333333;
                        margin-bottom: 5px;
                    }
                    
                    .header .timestamp {
                        font-size: 9pt;
                        color: #666666;
                    }
                    
                    .print-button, .info-button, .header-buttons, .info-dialog {
                        display: none !important;
                    }
                    
                    .main-cards-grid {
                        display: grid;
                        grid-template-columns: repeat(2, 1fr);
                        gap: 10px;
                        margin-bottom: 20px;
                        page-break-inside: avoid;
                    }
                    
                    .main-card {
                        background: #f8f9fa;
                        border: 1px solid #000000;
                        border-radius: 5px;
                        padding: 12px;
                        text-align: center;
                        page-break-inside: avoid;
                    }
                    
                    .main-card-icon {
                        font-size: 14pt;
                        margin-bottom: 5px;
                        color: #000000;
                    }
                    
                    .main-card-number {
                        font-size: 16pt;
                        font-weight: bold;
                        margin-bottom: 5px;
                        color: #000000;
                    }
                    
                    .main-card-label {
                        font-size: 9pt;
                        color: #333333;
                        font-weight: 600;
                    }
                    
                    .dashboard-metrics {
                        display: flex;
                        justify-content: space-around;
                        margin-top: 8px;
                        flex-wrap: wrap;
                    }
                    
                    .dashboard-metric {
                        text-align: center;
                        margin: 2px;
                    }
                    
                    .dashboard-metric-number {
                        font-size: 11pt;
                        font-weight: bold;
                        color: #000000;
                        display: block;
                    }
                    
                    .dashboard-metric-label {
                        font-size: 7pt;
                        color: #666666;
                    }
                    
                    .details-section {
                        background: #ffffff;
                        border: 1px solid #000000;
                        border-radius: 5px;
                        padding: 15px;
                        margin-bottom: 15px;
                        page-break-inside: avoid;
                    }
                    
                    .section-title {
                        font-size: 14pt;
                        font-weight: bold;
                        color: #000000;
                        margin-bottom: 12px;
                        padding-bottom: 5px;
                        border-bottom: 1px solid #000000;
                    }
                    
                    .info-grid {
                        display: grid;
                        grid-template-columns: 1fr 1fr;
                        gap: 10px;
                        margin-top: 10px;
                    }
                    
                    .info-card {
                        background: #f8f9fa;
                        border: 1px solid #cccccc;
                        border-radius: 3px;
                        padding: 10px;
                        page-break-inside: avoid;
                    }
                    
                    .info-card h3 {
                        color: #000000;
                        font-size: 11pt;
                        margin-bottom: 8px;
                        font-weight: 600;
                        border-bottom: 1px solid #cccccc;
                        padding-bottom: 4px;
                    }
                    
                    .list-item {
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        padding: 3px 0;
                        border-bottom: 1px solid #eeeeee;
                        font-size: 9pt;
                    }
                    
                    .list-item:last-child {
                        border-bottom: none;
                    }
                    
                    .list-text {
                        flex: 1;
                        color: #000000;
                    }
                    
                    .list-count {
                        background: #e9ecef;
                        color: #000000;
                        padding: 2px 6px;
                        border-radius: 8px;
                        font-weight: bold;
                        font-size: 8pt;
                        border: 1px solid #cccccc;
                    }
                    
                    .chart-container, .chart-container-dual {
                        background: #f8f9fa;
                        border-radius: 3px;
                        padding: 8px;
                        margin: 8px 0;
                        border: 1px solid #cccccc;
                    }
                    
                    .chart-legend-dual {
                        display: flex;
                        justify-content: center;
                        gap: 15px;
                        margin-bottom: 8px;
                        font-size: 8pt;
                    }
                    
                    .legend-item-dual {
                        display: flex;
                        align-items: center;
                        gap: 4px;
                        color: #000000;
                    }
                    
                    .legend-color {
                        width: 12px;
                        height: 8px;
                        border-radius: 2px;
                        border: 1px solid #666666;
                    }
                    
                    .reservation-color {
                        background: #e0e0e0;
                    }
                    
                    .immediate-color {
                        background: #cccccc;
                    }
                    
                    .chart-row-dual {
                        display: flex;
                        align-items: center;
                        margin-bottom: 6px;
                        padding: 2px 0;
                        font-size: 7pt;
                    }
                    
                    .chart-label-dual {
                        width: 35px;
                        font-weight: 600;
                        color: #000000;
                        font-size: 7pt;
                    }
                    
                    .chart-bars-container {
                        flex: 1;
                        display: flex;
                        flex-direction: column;
                        gap: 1px;
                        margin-left: 5px;
                    }
                    
                    .chart-bar-dual {
                        height: 10px;
                        background: #f0f0f0;
                        border-radius: 5px;
                        position: relative;
                        border: 1px solid #cccccc;
                    }
                    
                    .reservation-bar {
                        background: #f5f5f5;
                    }
                    
                    .immediate-bar {
                        background: #eeeeee;
                    }
                    
                    .reservation-fill {
                        height: 100%;
                        background: #e0e0e0;
                        border-radius: 5px;
                        position: relative;
                    }
                    
                    .immediate-fill {
                        height: 100%;
                        background: #cccccc;
                        border-radius: 5px;
                        position: relative;
                    }
                    
                    .chart-value-dual {
                        position: absolute;
                        right: 2px;
                        top: 50%;
                        transform: translateY(-50%);
                        font-weight: bold;
                        color: #000000;
                        font-size: 6pt;
                    }
                    
                    .chart-total {
                        width: 30px;
                        text-align: center;
                        font-weight: bold;
                        color: #000000;
                        font-size: 7pt;
                        margin-left: 5px;
                    }
                    
                    .revenue-breakdown {
                        display: grid;
                        grid-template-columns: 1fr 1fr;
                        gap: 8px;
                        margin: 8px 0;
                    }
                    
                    .breakdown-section {
                        background: #f8f9fa;
                        border-radius: 3px;
                        padding: 8px;
                        border: 1px solid #cccccc;
                    }
                    
                    .breakdown-section h4 {
                        color: #000000;
                        font-size: 9pt;
                        margin-bottom: 6px;
                        font-weight: 600;
                    }
                    
                    .total-section {
                        margin-top: 10px;
                        padding-top: 8px;
                        border-top: 1px solid #cccccc;
                        background: #f8f9fa;
                        border-radius: 3px;
                        padding: 8px;
                    }
                    
                    .footer {
                        text-align: center;
                        padding: 10px;
                        background: #f8f9fa;
                        border: 1px solid #cccccc;
                        border-radius: 5px;
                        margin-top: 20px;
                        color: #000000;
                        page-break-inside: avoid;
                        font-size: 9pt;
                    }
                    
                    @media print {
                        body {
                            padding: 10mm;
                            max-width: none;
                            font-size: 10pt;
                        }
                        
                        .details-section {
                            page-break-inside: avoid;
                            margin-bottom: 10px;
                        }
                        
                        .info-grid {
                            grid-template-columns: 1fr 1fr;
                        }
                        
                        .main-cards-grid {
                            grid-template-columns: repeat(2, 1fr);
                            gap: 8px;
                        }
                        
                        .header {
                            padding: 10px;
                        }
                        
                        .header h1 {
                            font-size: 16pt;
                        }
                    }
                </style>
                `;
                
                // Replace the existing CSS with print-friendly CSS
                const printContent = currentContent
                    .replace(/<style>[\s\S]*?<\/style>/g, printCSS)
                    .replace(/animate-on-scroll/g, '')
                    .replace(/onclick="[^"]*"/g, '')
                    .replace(/<div id="revenue-info-dialog"[\s\S]*?<\/div>\s*<\/div>/g, '')
                    .replace(/chart-container-dual/g, 'chart-container')
                    .replace(/chart-row-dual/g, 'chart-row')
                    .replace(/chart-label-dual/g, 'chart-label');
                
                // Create a new window with print-friendly content
                const printWindow = window.open('', '_blank');
                printWindow.document.write(printContent);
                printWindow.document.close();
                
                // Auto-print after a short delay
                setTimeout(() => {
                    printWindow.print();
                }, 1000);
            }
            
            // Smooth scrolling to sections
            function scrollToSection(sectionId) {
                const element = document.getElementById(sectionId);
                if (element) {
                    element.scrollIntoView({ 
                        behavior: 'smooth',
                        block: 'start'
                    });
                    
                    // Add highlight effect
                    element.style.animation = 'none';
                    setTimeout(() => {
                        element.style.animation = 'highlight 1s ease-in-out';
                    }, 100);
                }
            }
            
            // Add highlight animation CSS
            const highlightStyle = document.createElement('style');
            highlightStyle.textContent = `
                @keyframes highlight {
                    0% { border-color: var(--primary-yellow); }
                    50% { border-color: var(--dark-yellow); transform: scale(1.01); }
                    100% { border-color: var(--primary-yellow); }
                }
            `;
            document.head.appendChild(highlightStyle);
            
            // Advanced animations and interactions
            document.addEventListener('DOMContentLoaded', function() {
                // Intersection Observer for scroll animations
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('animated');
                        }
                    });
                }, {
                    threshold: 0.1,
                    rootMargin: '0px 0px -50px 0px'
                });
                
                // Observe all elements with animate-on-scroll class
                document.querySelectorAll('.animate-on-scroll').forEach(el => {
                    observer.observe(el);
                });
                
                // Animated counters
                function animateCounter(element, start, end, duration) {
                    let startTime = null;
                    const step = (timestamp) => {
                        if (!startTime) startTime = timestamp;
                        const progress = Math.min((timestamp - startTime) / duration, 1);
                        const current = Math.floor(progress * (end - start) + start);
                        element.textContent = current.toLocaleString();
                        if (progress < 1) {
                            requestAnimationFrame(step);
                        }
                    };
                    requestAnimationFrame(step);
                }
                
                // Animate main card numbers
                setTimeout(() => {
                    document.querySelectorAll('.main-card-number, .dashboard-metric-number').forEach(el => {
                        const finalValue = parseInt(el.textContent.replace(/[^0-9]/g, '')) || 0;
                        if (finalValue > 0) {
                            animateCounter(el, 0, finalValue, 2000);
                        }
                    });
                }, 500);
                
                // Chart bar animations
                setTimeout(() => {
                    document.querySelectorAll('.chart-fill, .reservation-fill, .immediate-fill').forEach((fill, index) => {
                        const width = fill.style.width;
                        fill.style.width = '0%';
                        setTimeout(() => {
                            fill.style.width = width;
                        }, 100 * index);
                    });
                }, 1000);
                
                // Add click ripple effect to main cards
                document.querySelectorAll('.main-card').forEach(card => {
                    card.addEventListener('click', function(e) {
                        const ripple = document.createElement('div');
                        ripple.style.position = 'absolute';
                        ripple.style.borderRadius = '50%';
                        ripple.style.background = 'rgba(255, 215, 0, 0.3)';
                        ripple.style.transform = 'scale(0)';
                        ripple.style.animation = 'ripple 0.6s linear';
                        ripple.style.left = (e.clientX - card.offsetLeft) + 'px';
                        ripple.style.top = (e.clientY - card.offsetTop) + 'px';
                        ripple.style.width = '20px';
                        ripple.style.height = '20px';
                        
                        card.style.position = 'relative';
                        card.appendChild(ripple);
                        
                        setTimeout(() => {
                            ripple.remove();
                        }, 600);
                    });
                });
            });
            
            // CSS for ripple animation
            const rippleStyle = document.createElement('style');
            rippleStyle.textContent = `
                @keyframes ripple {
                    to {
                        transform: scale(4);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(rippleStyle);
        </script>
        """

    def _analyze_basic_stats(self) -> Dict:
        """Analyze basic statistics for daily data."""
        logger.info("Analyzing basic daily statistics...")
        
        if not self.calls:
            logger.warning("No calls data for basic stats analysis")
            return {}
        
        unique_customers = len(set(call.get('callerPhone', '') for call in self.calls if call.get('callerPhone')))
        
        # Count customers with 2 or more calls today
        phone_counts = Counter(call.get('callerPhone', '') for call in self.calls if call.get('callerPhone'))
        repeat_customers = len([phone for phone, count in phone_counts.items() if count >= 2])
        
        # Separate reservations from immediate calls
        reservations = [call for call in self.calls if call.get('isReservation', False)]
        immediate_calls = [call for call in self.calls if not call.get('isReservation', False)]
        
        logger.info(f"Daily basic stats: {len(self.calls)} calls, {len(reservations)} reservations, {len(immediate_calls)} immediate calls")
        logger.info(f"Daily customers: {unique_customers} unique, {repeat_customers} repeat")
        
        return {
            'total_calls': len(self.calls),
            'unique_customers': unique_customers,
            'repeat_customers': repeat_customers,
            'reservations': len(reservations),
            'immediate_calls': len(immediate_calls),
            'reservation_percentage': (len(reservations) / len(self.calls) * 100) if self.calls else 0,
            'immediate_percentage': (len(immediate_calls) / len(self.calls) * 100) if self.calls else 0
        }

    def _analyze_time_patterns(self) -> Dict:
        """Analyze time patterns for daily data."""
        if not self.calls:
            return {}
        
        hours = [call['logTimestamp'].hour for call in self.calls]
        return {
            'hourly_distribution': dict(Counter(hours)),
            'peak_hour': Counter(hours).most_common(1)[0][0] if hours else 0
        }

    def _analyze_reservation_patterns(self) -> Dict:
        """Analyze reservation vs immediate call patterns with properly separated customer lists."""
        logger.info("Analyzing daily reservation patterns...")
        
        if not self.calls:
            logger.warning("No calls data for reservation analysis")
            return {}
        
        # Separate reservations from immediate calls
        reservations = [call for call in self.calls if call.get('isReservation', False)]
        immediate_calls = [call for call in self.calls if not call.get('isReservation', False)]
        
        logger.info(f"Found {len(reservations)} reservations and {len(immediate_calls)} immediate calls today")
        
        # Analyze reservation timing patterns
        reservation_hours = [call['logTimestamp'].hour for call in reservations]
        immediate_hours = [call['logTimestamp'].hour for call in immediate_calls]
        
        # Top customers for each type - THIS WAS THE MISSING PIECE
        reservation_customers = Counter(call.get('callerPhone', '') for call in reservations if call.get('callerPhone'))
        immediate_customers = Counter(call.get('callerPhone', '') for call in immediate_calls if call.get('callerPhone'))
        
        # Geographic analysis - locations for reservations
        reservation_locations = [call.get('roadName', '') for call in reservations if call.get('roadName') and call.get('roadName').strip()]
        immediate_locations = [call.get('roadName', '') for call in immediate_calls if call.get('roadName') and call.get('roadName').strip()]
        
        logger.info(f"Processed {len(reservation_customers)} reservation customers and {len(immediate_customers)} immediate customers")
        logger.info(f"Processed {len(reservation_locations)} reservation locations and {len(immediate_locations)} immediate locations")
        
        return {
            'total_reservations': len(reservations),
            'total_immediate': len(immediate_calls),
            'reservation_percentage': (len(reservations) / len(self.calls) * 100) if self.calls else 0,
            'immediate_percentage': (len(immediate_calls) / len(self.calls) * 100) if self.calls else 0,
            'reservation_hours': dict(Counter(reservation_hours)),
            'immediate_hours': dict(Counter(immediate_hours)),
            'reservation_peak_hour': Counter(reservation_hours).most_common(1)[0][0] if reservation_hours else 0,
            'immediate_peak_hour': Counter(immediate_hours).most_common(1)[0][0] if immediate_hours else 0,
            'top_reservation_customers': reservation_customers.most_common(10),
            'top_immediate_customers': immediate_customers.most_common(10),
            'top_reservation_locations': Counter(reservation_locations).most_common(10),
            'top_immediate_locations': Counter(immediate_locations).most_common(10)
        }

    def _analyze_customers(self) -> Dict:
        """Analyze customer data for daily data."""
        logger.info("Analyzing daily customer data...")
        
        if not self.calls:
            logger.warning("No calls data for customer analysis")
            return {}
        
        phone_counts = Counter(call.get('callerPhone', '') for call in self.calls if call.get('callerPhone'))
        logger.info(f"Found {len(phone_counts)} unique phone numbers today")
        
        return {
            'top_customers': phone_counts.most_common(10)
        }

    def _analyze_locations(self) -> Dict:
        """Analyze location data for daily data."""
        logger.info("Analyzing daily location data...")
        
        if not self.calls:
            logger.warning("No calls data for location analysis")
            return {}
        
        pickup_locations = [call.get('roadName', '') for call in self.calls if call.get('roadName') and call.get('roadName').strip()]
        logger.info(f"Found {len(pickup_locations)} pickup locations today")
        
        return {
            'top_pickup_locations': Counter(pickup_locations).most_common(10)
        }

    def _generate_error_report(self, error_message: str) -> str:
        """Generate an error report in Greek."""
        return f"""
        <!DOCTYPE html>
        <html lang="el">
        <head>
            <title>Σφάλμα - Daily Taxi Analytics</title>
            <style>
                body {{
                    font-family: Arial, sans-serif;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    height: 100vh;
                    margin: 0;
                    background: linear-gradient(135deg, #000000 0%, #1a1a1a 100%);
                    color: #FFD700;
                }}
                .error-container {{
                    text-align: center;
                    padding: 50px;
                    background: rgba(255, 215, 0, 0.1);
                    border: 2px solid #FFD700;
                    border-radius: 20px;
                }}
            </style>
        </head>
        <body>
            <div class="error-container">
                <h1>❌ Σφάλμα</h1>
                <p>{error_message}</p>
                <p>Παρακαλώ ελέγξτε το αρχείο καταγραφής και δοκιμάστε ξανά.</p>
            </div>
        </body>
        </html>
        """

    def run_analysis(self, file_path: str) -> str:
        """Run the complete daily analysis and generate the report."""
        try:
            logger.info(f"Starting daily analysis for file: {file_path}")
            
            # Parse the log file
            self.calls = self.parse_log_file(file_path)
            
            if not self.calls:
                logger.error("No valid call data found")
                return self._generate_error_report("Δεν βρέθηκαν έγκυρα δεδομένα κλήσεων στο ημερήσιο αρχείο καταγραφής")
            
            logger.info(f"Starting HTML daily report generation...")
            
            # Generate the HTML report
            html_content = self.generate_premium_html_report(file_path)
            
            logger.info(f"HTML daily report generated successfully, saving to file...")
            
            # Create output directory
            output_config = self.config['output']
            output_dir = output_config['output_dir']
            os.makedirs(output_dir, exist_ok=True)
            
            # Save the report
            current_date = datetime.now().strftime('%Y-%m-%d_%H-%M-%S')
            output_file = os.path.join(output_dir, f"daily_taxi_report_{current_date}.html")
            
            with open(output_file, 'w', encoding='utf-8') as f:
                f.write(html_content)
            
            logger.info(f"Daily report generated successfully: {output_file}")
            return output_file
            
        except Exception as e:
            logger.error(f"Error during daily analysis: {e}")
            import traceback
            logger.error(f"Full traceback: {traceback.format_exc()}")
            return self._generate_error_report(f"Η ημερήσια ανάλυση απέτυχε: {str(e)}")

def main():
    """Main function to run the daily Greek taxi analytics."""
    import argparse
    
    parser = argparse.ArgumentParser(description='📞 Ημερήσια Αναλυτική Auto Call Center - JSON Only Configuration')
    parser.add_argument('file_path', nargs='?', default='/tmp/register_call_v5.log', 
                       help='Διαδρομή προς το ημερήσιο αρχείο καταγραφής')
    parser.add_argument('--config', help='Διαδρομή προς το αρχείο διαμόρφωσης (δεν χρησιμοποιείται - μόνο analytics.json)')
    
    args = parser.parse_args()
    
    print("🚀 Έναρξη Ημερήσιας Αναλυτικής Auto Call Center (JSON Only Mode)...")
    print(f"📊 Ανάλυση ημερήσιων δεδομένων: {args.file_path}")
    print(f"📋 Χρήση αρχείου: register_call_v5.log")
    print(f"⚙️ Ρυθμίσεις: ΜΟΝΟ από analytics.json (απαιτείται!)")
    print(f"❗ ΠΡΟΣΟΧΗ: Το analytics.json ΠΡΕΠΕΙ να υπάρχει με όλες τις απαραίτητες ρυθμίσεις!")
    
    # Create analytics engine - it will check for analytics.json and exit if not found
    try:
        engine = TaxiAnalyticsEngine()
    except SystemExit:
        # analytics.json validation failed
        return
    
    # Run analysis
    result = engine.run_analysis(args.file_path)
    
    if result and result.endswith('.html'):
        print(f"✅ Η ημερήσια αναφορά δημιουργήθηκε επιτυχώς!")
        print(f"📄 Αναφορά αποθηκεύτηκε στο: {result}")
        print(f"🌐 Ανοίξτε το αρχείο στον περιηγητή σας για να δείτε την ημερήσια αναφορά!")
        print(f"🖨️ Χρησιμοποιήστε το κουμπί εκτύπωσης για οικολογική εκτύπωση!")
        print(f"⚙️ Όλες οι ρυθμίσεις παίρνονται ΜΟΝΟ από το analytics.json!")
        print(f"💰 Περιλαμβάνει ημερήσια/νυχτερινά τιμολόγια για ακριβή έσοδα!")
        print(f"🎉 Περιλαμβάνει ελληνική διεπαφή με κίτρινο/μαύρο θέμα για ημερήσια δεδομένα! 🎉")
    else:
        print("❌ Η δημιουργία ημερήσιας αναφοράς απέτυχε.")

if __name__ == "__main__":
    main()