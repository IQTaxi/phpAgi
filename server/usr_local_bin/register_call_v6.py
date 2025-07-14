#!/usr/bin/env python3
import sys
import json
import requests
import os
import logging

# Ρύθμιση καταγραφής σε αρχείο για αποσφαλμάτωση
logging.basicConfig(
    filename='/tmp/register_call_v6.log',
    level=logging.DEBUG,
    format='%(asctime)s - %(levelname)s - %(message)s'
)

def load_config(filepath):
    try:
        with open(filepath, 'r', encoding='utf-8') as f:
            return json.load(f)
    except Exception as e:
        logging.error(f"Αποτυχία φόρτωσης αρχείου ρυθμίσεων {filepath}: {e}")
        return None

def load_json_data(json_file_path):
    if not os.path.exists(json_file_path):
        logging.error(f"Το αρχείο JSON δεν βρέθηκε: {json_file_path}")
        return None
    
    try:
        with open(json_file_path, 'r', encoding='utf-8') as f:
            data = json.load(f)
        
        # Επαλήθευση απαιτούμενων πεδίων
        required_fields = ["phone", "name", "pickup", "pickupLocation", "destination"]
        missing_fields = [field for field in required_fields if field not in data]
        if missing_fields:
            logging.error(f"Λείπουν πεδία στο JSON: {', '.join(missing_fields)}")
            return None
        
        # Επαλήθευση ένθετων πεδίων τοποθεσίας
        if "latLng" not in data["pickupLocation"] or "lat" not in data["pickupLocation"]["latLng"] or "lng" not in data["pickupLocation"]["latLng"]:
            logging.error("Μη έγκυρη δομή pickupLocation.latLng στο JSON")
            return None
        
        # Επαλήθευση μη κενών πεδίων
        if not data["name"]:
            logging.error("Το όνομα είναι κενό στο JSON")
            return None
        if not data["pickup"]:
            logging.error("Η διεύθυνση παραλαβής είναι κενή στο JSON")
            return None
        if not data["destination"]:
            logging.error("Ο προορισμός είναι κενός στο JSON")
            return None
        if not data["phone"]:
            logging.error("Ο αριθμός τηλεφώνου είναι κενός στο JSON")
            return None
        
        # Επαλήθευση αριθμού τηλεφώνου (μόνο μη κενό string)
        phone = str(data["phone"])
        if not phone:
            logging.error("Ο αριθμός τηλεφώνου είναι κενός μετά τη μετατροπή")
            return None
        
        return data
    except json.JSONDecodeError as e:
        logging.error(f"Σφάλμα αποκωδικοποίησης JSON: {e}")
        return None
    except Exception as e:
        logging.error(f"Σφάλμα ανάγνωσης JSON: {e}")
        return None

def print_result_json(call_operator, msg):
    """Print the result as JSON"""
    result = {
        "callOperator": call_operator,
        "msg": msg
    }
    print(json.dumps(result, ensure_ascii=False))

def register_call(current_exten, json_file_path, external_reference_id):
    # Φόρτωση ρυθμίσεων
    config = load_config('/usr/local/bin/config.json')
    if config is None:
        print_result_json(True, "Κάτι πήγε στραβά με την καταχώρηση της διαδρομής σας")
        return
    
    # Έλεγχος αν υπάρχει το extension στο config
    if current_exten not in config:
        logging.error(f"Το extension {current_exten} δεν βρέθηκε στις ρυθμίσεις")
        print_result_json(True, "Κάτι πήγε στραβά με την καταχώρηση της διαδρομής σας")
        return
    
    extension_config = config[current_exten]
    accessToken = extension_config.get("clientToken")
    base_url = extension_config.get("registerBaseUrl")
    days_valid = extension_config.get("daysValid")
    
    if not accessToken or not base_url:
        logging.error(f"Λείπει το clientToken ή το registerBaseUrl για το extension {current_exten}")
        print_result_json(True, "Κάτι πήγε στραβά με την καταχώρηση της διαδρομής σας")
        return
    
    # Επαλήθευση του daysValid - πρέπει να είναι ακέραιος αριθμός
    if days_valid is None:
        logging.error(f"Λείπει το daysValid για το extension {current_exten}")
        print_result_json(True, "Κάτι πήγε στραβά με την καταχώρηση της διαδρομής σας")
        return
    
    try:
        days_valid = int(days_valid)
    except (ValueError, TypeError):
        logging.error(f"Μη έγκυρη τιμή daysValid για το extension {current_exten}: {days_valid}")
        print_result_json(True, "Κάτι πήγε στραβά με την καταχώρηση της διαδρομής σας")
        return
    
    # Φόρτωση και ανάλυση δεδομένων JSON
    data = load_json_data(json_file_path)
    if data is None:
        print_result_json(True, "Κάτι πήγε στραβά με την καταχώρηση της διαδρομής σας")
        return
    
    # Εξαγωγή πεδίων από το JSON
    caller_phone = str(data["phone"])  # Μετατροπή σε string για συμβατότητα με το API
    customer_name = data["name"]
    road_name = data["pickup"]
    comments = data["comments"] if "comments" in data else ""
    pickup_lat = data["pickupLocation"]["latLng"]["lat"]
    pickup_lng = data["pickupLocation"]["latLng"]["lng"]
    destination = data["destination"]
    reservation_date = None
    if "reservationStamp" not in data:
        reservation_date = None
    else:
        reservation_date = data["reservationStamp"]
    
    try:
        if "latLng" not in data["destinationLocation"] or "lat" not in data["destinationLocation"]["latLng"] or "lng" not in data["destinationLocation"]["latLng"]:
            dest_lat = 0
            dest_lng = 0
        else:
            dest_lat = data["destinationLocation"]["latLng"]["lat"]
            dest_lng = data["destinationLocation"]["latLng"]["lng"]
    except Exception as e:
        dest_lat = 0
        dest_lng = 0
    
    # Κατασκευή αιτήματος API
    url = base_url.rstrip("/") + "/api/Calls/RegisterNoLogin"
    headers = {
        "Authorization": accessToken,
        "Content-Type": "application/json; charset=UTF-8",
    }
    
    payload = {
        "callTimeStamp": reservation_date,
        "callerPhone": caller_phone,
        "customerName": customer_name,
        "roadName": road_name,
        "latitude": pickup_lat,
        "longitude": pickup_lng,
        "destination": destination,
        "destLatitude": dest_lat,
        "destLongitude": dest_lng,
        "taxisNo": 1,
        "comments": "[ΑΥΤΟΜΑΤΟΠΟΙΗΜΕΝΗ ΚΛΗΣΗ] "+comments,
        "referencePath": external_reference_id,
        "daysValid": days_valid
    }
    
    logging.debug(f"Φορτίο API: {json.dumps(payload, ensure_ascii=False)}")
    
    try:
        response = requests.post(url, headers=headers, json=payload, timeout=30)
        response.raise_for_status()
        api_response = response.json()
        logging.debug(f"Απάντηση API: {json.dumps(api_response, ensure_ascii=False)}")
        
        # Extract result data
        result_data = api_response.get("result", {})
        result_code = result_data.get("resultCode", -1)
        msg = result_data.get("msg", "").strip()
        
        # Set callOperator based on resultCode
        call_operator = (result_code != 0)
        
        # Use default message if msg is empty
        if not msg:
            msg = "Κάτι πήγε στραβά με την καταχώρηση της διαδρομής σας"
        
        print_result_json(call_operator, msg)
        
    except requests.RequestException as e:
        logging.error(f"Σφάλμα αιτήματος API: {e}")
        print_result_json(True, "Κάτι πήγε στραβά με την καταχώρηση της διαδρομής σας")
    except json.JSONDecodeError:
        logging.error("Μη έγκυρη απόκριση JSON από το API")
        print_result_json(True, "Κάτι πήγε στραβά με την καταχώρηση της διαδρομής σας")
    except Exception as e:
        logging.error(f"Απροσδόκητο σφάλμα: {e}")
        print_result_json(True, "Κάτι πήγε στραβά με την καταχώρηση της διαδρομής σας")

if __name__ == "__main__":
    if len(sys.argv) != 4:
        print_result_json(True, "Κάτι πήγε στραβά με την καταχώρηση της διαδρομής σας")
        sys.exit(1)
    
    current_exten = sys.argv[1]
    json_file_path = sys.argv[2]
    external_reference_id = sys.argv[3]
    
    try:
        register_call(current_exten, json_file_path, external_reference_id)
    except Exception as e:
        logging.error(f"Αποτυχία εκτέλεσης σεναρίου: {e}")
        print_result_json(True, "Κάτι πήγε στραβά με την καταχώρηση της διαδρομής σας")
        sys.exit(1)