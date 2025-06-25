#!/usr/bin/env python3

import sys
import json
import requests
import os
import logging

# Ρύθμιση καταγραφής σε αρχείο για αποσφαλμάτωση
logging.basicConfig(
    filename='/tmp/register_call_v4.log',
    level=logging.DEBUG,
    format='%(asctime)s - %(levelname)s - %(message)s'
)

def load_config(filepath):
    try:
        with open(filepath, 'r', encoding='utf-8') as f:
            return json.load(f)
    except Exception as e:
        logging.error(f"Αποτυχία φόρτωσης αρχείου ρυθμίσεων {filepath}: {e}")
        print("Σφάλμα: Αποτυχία φόρτωσης αρχείου ρυθμίσεων")
        sys.exit(1)

def load_json_data(json_file_path):
    if not os.path.exists(json_file_path):
        logging.error(f"Το αρχείο JSON δεν βρέθηκε: {json_file_path}")
        raise FileNotFoundError("Σφάλμα: Το αρχείο JSON δεν βρέθηκε")
    try:
        with open(json_file_path, 'r', encoding='utf-8') as f:
            data = json.load(f)

        # Επαλήθευση απαιτούμενων πεδίων
        required_fields = ["phone", "name", "pickup", "pickupLocation", "destination"]
        missing_fields = [field for field in required_fields if field not in data]
        if missing_fields:
            logging.error(f"Λείπουν πεδία στο JSON: {', '.join(missing_fields)}")
            raise ValueError(f"Σφάλμα: Λείπουν πεδία: {', '.join(missing_fields)}")

        # Επαλήθευση ένθετων πεδίων τοποθεσίας
        if "latLng" not in data["pickupLocation"] or "lat" not in data["pickupLocation"]["latLng"] or "lng" not in data["pickupLocation"]["latLng"]:
            logging.error("Μη έγκυρη δομή pickupLocation.latLng στο JSON")
            raise ValueError("Σφάλμα: Μη έγκυρη δομή τοποθεσίας παραλαβής")

        # Επαλήθευση μη κενών πεδίων
        if not data["name"]:
            logging.error("Το όνομα είναι κενό στο JSON")
            raise ValueError("Σφάλμα: Το όνομα είναι κενό")
        if not data["pickup"]:
            logging.error("Η διεύθυνση παραλαβής είναι κενή στο JSON")
            raise ValueError("Σφάλμα: Η διεύθυνση παραλαβής είναι κενή")
        if not data["destination"]:
            logging.error("Ο προορισμός είναι κενός στο JSON")
            raise ValueError("Σφάλμα: Ο προορισμός είναι κενός")
        if not data["phone"]:
            logging.error("Ο αριθμός τηλεφώνου είναι κενός στο JSON")
            raise ValueError("Σφάλμα: Ο αριθμός τηλεφώνου είναι κενός")

        # Επαλήθευση αριθμού τηλεφώνου (μόνο μη κενό string)
        phone = str(data["phone"])
        if not phone:
            logging.error("Ο αριθμός τηλεφώνου είναι κενός μετά τη μετατροπή")
            raise ValueError("Σφάλμα: Ο αριθμός τηλεφώνου είναι κενός")

        return data
    except json.JSONDecodeError as e:
        logging.error(f"Σφάλμα αποκωδικοποίησης JSON: {e}")
        raise ValueError("Σφάλμα: Μη έγκυρη μορφή JSON")
    except Exception as e:
        logging.error(f"Σφάλμα ανάγνωσης JSON: {e}")
        raise ValueError(f"Σφάλμα: Αποτυχία ανάγνωσης JSON: {e}")

def register_call(current_exten, json_file_path):
    # Φόρτωση ρυθμίσεων
    config = load_config('/usr/local/bin/config.json')
    
    # Έλεγχος αν υπάρχει το extension στο config
    if current_exten not in config:
        logging.error(f"Το extension {current_exten} δεν βρέθηκε στις ρυθμίσεις")
        print(f"Σφάλμα: Το extension {current_exten} δεν βρέθηκε στις ρυθμίσεις")
        sys.exit(1)
    
    extension_config = config[current_exten]
    accessToken = extension_config.get("clientToken")
    base_url = extension_config.get("registerBaseUrl")

    if not accessToken or not base_url:
        logging.error(f"Λείπει το clientToken ή το registerBaseUrl για το extension {current_exten}")
        print("Σφάλμα: Λείπουν παράμετροι ρυθμίσεων")
        sys.exit(1)

    # Φόρτωση και ανάλυση δεδομένων JSON
    try:
        data = load_json_data(json_file_path)
    except Exception as e:
        print(str(e))
        sys.exit(1)

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
        "comments": "[ΑΥΤΟΜΑΤΟΠΟΙΗΜΕΝΗ ΚΛΗΣΗ] "+comments
    }

    logging.debug(f"Φορτίο API: {json.dumps(payload, ensure_ascii=False)}")

    try:
        response = requests.post(url, headers=headers, json=payload, timeout=30)
        response.raise_for_status()
        data = response.json()

        logging.debug(f"Απάντηση API: {json.dumps(data, ensure_ascii=False)}")

        if data.get("response", {}).get("id", 0) > 0:
            print("Σας ευχαριστούμε που καλέσατε. Σύντομα θα ενημερωθείτε για την εξέλιξη της διαδρομής σας")
            return
        else:
            logging.error(f"Αποτυχία καταχώρησης κλήσης: {json.dumps(data, ensure_ascii=False)}")
            print("Σφάλμα: Αποτυχία καταχώρησης της κλήσης. Παρακαλώ προσπαθήστε ξανά.")
    except requests.RequestException as e:
        logging.error(f"Σφάλμα αιτήματος API: {e}")
        print(f"Σφάλμα: Αποτυχία σύνδεσης με τον διακομιστή: {e}")
    except json.JSONDecodeError:
        logging.error("Μη έγκυρη απόκριση JSON από το API")
        print("Σφάλμα: Μη έγκυρη απόκριση διακομιστή")
    except Exception as e:
        logging.error(f"Απροσδόκητο σφάλμα: {e}")
        print(f"Σφάλμα: {e}")

if __name__ == "__main__":
    if len(sys.argv) != 3:
        print("Σφάλμα: Χρήση: python register_call_v3.py <extension> <αρχείο_json>")
        sys.exit(1)

    current_exten = sys.argv[1]
    json_file_path = sys.argv[2]

    try:
        register_call(current_exten, json_file_path)
    except Exception as e:
        logging.error(f"Αποτυχία εκτέλεσης σεναρίου: {e}")
        print(str(e))
        sys.exit(1)