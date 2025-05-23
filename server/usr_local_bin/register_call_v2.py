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

config = load_config('/usr/local/bin/config.json')

accessToken = config.get("clientToken")
base_url = config.get("registerBaseUrl")

if not accessToken or not base_url:
    print("❌ Δεν βρέθηκε το clientToken ή το registerBaseUrl στο αρχείο ρυθμίσεων")
    sys.exit(1)

def read_text_file(path, label):
    if not os.path.exists(path):
        raise FileNotFoundError(f"❌ Το αρχείο για {label} δεν βρέθηκε: {path}")
    with open(path, 'r', encoding='utf-8') as f:
        content = f.read().strip()
        if not content:
            raise ValueError(f"❌ Το αρχείο για {label} είναι κενό: {path}")
        return content

def read_latlng_json(path, label):
    if not os.path.exists(path):
        raise FileNotFoundError(f"❌ Το αρχείο lat/lng για {label} δεν βρέθηκε: {path}")
    with open(path, 'r', encoding='utf-8') as f:
        data = json.load(f)
        lat = data.get("lat")
        lng = data.get("lng")
        if lat is None or lng is None:
            raise ValueError(f"❌ Το αρχείο lat/lng για {label} δεν περιέχει έγκυρα δεδομένα: {path}")
        return lat, lng

def register_call(caller_phone, name_file, pickup_file, pickup_latlng_file, dest_file, dest_latlng_file):
    customer_name = read_text_file(name_file, "όνομα")
    road_name = read_text_file(pickup_file, "διεύθυνση παραλαβής")
    dest_name = read_text_file(dest_file, "προορισμός")

    pickup_lat, pickup_lng = read_latlng_json(pickup_latlng_file, "παραλαβής")
    dest_lat, dest_lng = read_latlng_json(dest_latlng_file, "προορισμού")

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
        "destination": dest_name,
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
        print("❌ Λανθασμένη χρήση.\nΧρήση: python register_call_v2.py <τηλέφωνο> <αρχείο_ονόματος> <αρχείο_διεύθυνσης_παραλαβής> <αρχείο_latlng_παραλαβής.json> <αρχείο_προορισμού> <αρχείο_latlng_προορισμού.json>")
        sys.exit(1)

    _, caller_phone, name_file, pickup_file, pickup_latlng_file, dest_file, dest_latlng_file = sys.argv

    try:
        register_call(caller_phone, name_file, pickup_file, pickup_latlng_file, dest_file, dest_latlng_file)
    except Exception as e:
        print(str(e))

