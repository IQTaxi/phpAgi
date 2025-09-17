# Google APIs Required for AGI Call Handler System

## Overview
This document outlines all Google Cloud APIs that need to be enabled in Google Cloud Console for the AGI Call Handler system to function properly.

## Required Google APIs

### 1. Cloud Speech-to-Text API
- **API Name**: `speech.googleapis.com`
- **Service Endpoint**: `https://speech.googleapis.com/v1/speech:recognize`
- **Purpose**: Converts customer voice input to text
- **Usage**:
  - Customer name recognition
  - Pickup address voice input
  - Destination address voice input
  - Reservation date/time voice input
- **Languages Supported**: Greek (el-GR) and others
- **Cost**: Pay per minute of audio processed

### 2. Cloud Text-to-Speech API
- **API Name**: `texttospeech.googleapis.com`
- **Service Endpoint**: `https://texttospeech.googleapis.com/v1/text:synthesize`
- **Purpose**: Generates speech confirmation messages
- **Usage**:
  - Booking confirmation audio
  - Address verification messages
  - System prompts and instructions
- **Languages Supported**: Greek (el-GR) and others
- **Cost**: Pay per character of text synthesized

### 3. Maps Geocoding API (Legacy Option)
- **API Name**: `geocoding-backend.googleapis.com`
- **Service Endpoint**: `https://maps.googleapis.com/maps/api/geocode/json`
- **Purpose**: Converts addresses to GPS coordinates
- **Usage**:
  - Pickup location geocoding
  - Destination location geocoding
  - Address validation and formatting
- **Used When**: Configuration setting `geocodingApiVersion = 1`
- **Cost**: Pay per geocoding request

### 4. Places API (New Text Search) (Recommended Option)
- **API Name**: `places-backend.googleapis.com`
- **Service Endpoint**: `https://places.googleapis.com/v1/places:searchText`
- **Purpose**: Advanced address search and geocoding with better accuracy
- **Usage**:
  - Enhanced pickup location search
  - Enhanced destination location search
  - More accurate address matching
- **Used When**: Configuration setting `geocodingApiVersion = 2`
- **Cost**: Pay per text search request (higher cost but better accuracy)

### 5. Cloud Translation API
- **API Name**: `translate.googleapis.com`
- **Service Endpoint**: `https://translation.googleapis.com/language/translate/v2`
- **Purpose**: Translates text between different languages
- **Usage**:
  - Date and time parsing in multiple languages
  - Address translation for better geocoding accuracy
  - Multi-language support for customer input processing
- **Languages Supported**: Greek to English translation and vice versa
- **Cost**: Pay per character translated

## Setup Instructions

### Step 1: Google Cloud Console Setup
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select an existing project
3. Enable billing for your project (required for all APIs)

### Step 2: Enable Required APIs
Navigate to **APIs & Services > Library** and enable:
- Cloud Speech-to-Text API
- Cloud Text-to-Speech API
- Cloud Translation API
- Maps Geocoding API (if using legacy geocoding)
- Places API (if using new geocoding)

### Step 3: Create API Key
1. Go to **APIs & Services > Credentials**
2. Click **+ CREATE CREDENTIALS > API Key**
3. Copy the generated API key
4. **IMPORTANT**: Restrict the API key to only the required APIs for security

### Step 4: Configure API Key Restrictions
For security, restrict your API key to only these APIs:
- Cloud Speech-to-Text API
- Cloud Text-to-Speech API
- Cloud Translation API
- Maps Geocoding API (optional)
- Places API (optional)

### Step 5: Update Configuration
Add the API key to your `config.php` file:
```php
$config['YOUR_EXTENSION']['googleApiKey'] = 'YOUR_API_KEY_HERE';
```



## Support

For issues with Google APIs:
- [Google Cloud Support](https://cloud.google.com/support)
- [API Documentation](https://cloud.google.com/docs)

For AGI system issues:
- Check system logs in `/tmp/auto_register_call/`
- Review analytics at `agi_analytics.php`