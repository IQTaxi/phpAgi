#!/usr/bin/env python3

import sys
import json
import requests
import os

def load_config(filepath):
    try:
        with open(filepath, 'r', encoding='utf-8') as f:
            return json.load(f)
    except Exception as e:
        print(f"❌ Σφάλμα κατά τη φόρτωση του αρχείου ρυθμίσεων: {e}")
        sys.exit(1)

config = load_config('/usr/local/bin/config.json')  # Adjust path as needed

accessToken = config.get("clientToken")
base_url = config.get("registerBaseUrl")

if not accessToken or not base_url:
    print("❌ Δεν βρέθηκε το clientToken ή το registerBaseUrl στο αρχείο ρυθμίσεων")
    sys.exit(1)

def read_text_from_file(txt_file_path, label):
    if not os.path.exists(txt_file_path):
        raise FileNotFoundError(f"❌ Το αρχείο για {label} δεν βρέθηκε: {txt_file_path}")
    with open(txt_file_path, "r", encoding="utf-8") as file:
        content = file.read().strip()
        if not content:
            if label == "όνομα":
                raise ValueError("❌ Δεν αναγνωρίστηκε το όνομα σας. Παρακαλώ προσπαθήστε ξανά.")
            elif label == "διεύθυνση παραλαβής":
                raise ValueError("❌ Δεν αναγνωρίστηκε η διεύθυνση παραλαβής. Παρακαλώ προσπαθήστε ξανά.")
            elif label == "προορισμός":
                raise ValueError("❌ Δεν αναγνωρίστηκε η διεύθυνση προορισμού. Παρακαλώ προσπαθήστε ξανά.")
            else:
                raise ValueError(f"❌ Το αρχείο για {label} είναι κενό: {txt_file_path}")
        return content

def read_latlng_file(latlng_path, label):
    if not latlng_path or not os.path.exists(latlng_path):
        raise ValueError(f"❌ Η τοποθεσία {label} δεν βρέθηκε ή το αρχείο δεν υπάρχει: {latlng_path}")
    try:
        with open(latlng_path, "r", encoding="utf-8") as f:
            latlng = f.read().strip().split(",")
            if len(latlng) != 2:
                raise ValueError(f"❌ Η τοποθεσία {label} δεν είναι έγκυρη (χρειάζονται δύο τιμές με κόμμα): {latlng_path}")
            lat = float(latlng[0])
            lng = float(latlng[1])
            return lat, lng
    except Exception as e:
        raise ValueError(f"❌ Σφάλμα στην ανάγνωση της τοποθεσίας {label} από το αρχείο '{latlng_path}': {e}")

def register_call(caller_phone, name_file, pickup_file, pickup_latlng_file, dest_file, dest_latlng_file):
    customer_name = read_text_from_file(name_file, "όνομα")
    road_name = read_text_from_file(pickup_file, "διεύθυνση παραλαβής")
    destination = read_text_from_file(dest_file, "προορισμός")
    pickup_lat, pickup_lng = read_latlng_file(pickup_latlng_file, "παραλαβής")
    dest_lat, dest_lng = read_latlng_file(dest_latlng_file, "προορισμού")

    url = base_url.rstrip("/") + "/api/Calls/RegisterNoLogin"
    headers = {
        "Authorization": accessToken,
        "Content-Type": "application/json; charset=UTF-8",
    }

    payload = {
        "callerPhone": caller_phone,
        "customerName": customer_name,
        "roadName": road_name,
        "latitude": pickup_lat,
        "longitude": pickup_lng,
        "destination": destination,
        "destLatitude": dest_lat,
        "destLongitude": dest_lng,
        "taxisNo": 1,
        "comments": "[ΑΥΤΟΜΑΤΟΠΟΙΗΜΕΝΗ ΚΛΗΣΗ]"
    }

    try:
        response = requests.post(url, headers=headers, json=payload, timeout=30)
        response.raise_for_status()
        data = response.json()

        if data.get("response", {}).get("id", 0) > 0:
            print("✅ Σας ευχαριστούμε πολύ που καλέσατε. Ο οδηγός θα είναι κοντά σας σε λίγα λεπτά. Καλή σας διαδρομή!")
        else:
            print("❌ Αποτυχία καταχώρησης κλήσης. Απάντηση από τον διακομιστή:")
            print(json.dumps(data, indent=2, ensure_ascii=False))
    except requests.RequestException as e:
        print(f"❌ Σφάλμα σύνδεσης με τον διακομιστή: {e}")
    except json.JSONDecodeError:
        print("❌ Σφάλμα στην αποκωδικοποίηση της απάντησης JSON.")
    except Exception as e:
        print(f"❌ Σφάλμα: {e}")

if __name__ == "__main__":
    if len(sys.argv) != 7:
        print("❌ Λανθασμένη χρήση.\nΧρήση: python register_call.py <τηλέφωνο> <αρχείο_ονόματος> <αρχείο_διεύθυνσης_παραλαβής> <αρχείο_latlng_παραλαβής> <αρχείο_προορισμού> <αρχείο_latlng_προορισμού>")
        sys.exit(1)

    _, caller_phone, name_file, pickup_file, pickup_latlng_file, dest_file, dest_latlng_file = sys.argv

    try:
        register_call(caller_phone, name_file, pickup_file, pickup_latlng_file, dest_file, dest_latlng_file)
    except Exception as e:
        print(str(e))