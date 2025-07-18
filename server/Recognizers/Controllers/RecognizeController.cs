﻿using Microsoft.AspNetCore.Http;
using Microsoft.AspNetCore.Mvc;
using Microsoft.Recognizers.Text;
using Microsoft.Recognizers.Text.DateTime;
using Recognizers.Models;
using System.Net.Http;
using System.Threading.Tasks;

namespace Recognizers.Controllers
{
    [Route("api/[controller]/[action]")]
    [ApiController]
    public class RecognizeController : ControllerBase
    {
        [HttpGet]
        public IActionResult Get()
        {
            return Ok("Hello from RecognizeController!");
        }

        [HttpPost]
        public async Task<RecognizeResponse> DateFree([FromBody] RecognizeRequest input)
        {
            int checkTranslation = 0;
            string translation = "";
            string text = input.input;
            RecognizeResponse recognizeResponse = new RecognizeResponse
            {
                InputText = input.input,
                TranslationText = "",
                Matches = new List<ModelResult>()
            };
            while (checkTranslation<TranslateBaseUrls.Length && translation.Length == 0)
            {
                TranslateResponse translateData = await GetTranslationFree(input.input, input.translateFrom,input.translateTo);
                if (translateData == null || string.IsNullOrEmpty(translateData.translation))
                {
                    ++checkTranslation;
                    continue;
                }
                else
                {
                    translation = translateData.translation;
                    recognizeResponse.TranslationText = translation;
                    text = translation;
                    break;
                }
            }

            recognizeResponse.Matches= DateTimeRecognizer.RecognizeDateTime(text, input.matchLang, DateTimeOptions.CalendarMode);

            return recognizeResponse;
        }

        int lastUsedIndex = -1;
        private static string[] TranslateBaseUrls = new string[]
        {
            "https://lingva.ml",
            "https://lingva.lunar.icu",
            "https://translate.plausibility.cloud"
        };

        int getTranslationAPIIndex()
        {
            if (lastUsedIndex < 0)
            {
                Random rnd = new Random();
                lastUsedIndex = rnd.Next(0, TranslateBaseUrls.Length-1);
                
            }
            else
            {
                lastUsedIndex = (lastUsedIndex + 1) % TranslateBaseUrls.Length;
            }
                
            return lastUsedIndex;
        }

        private async Task<TranslateResponse> GetTranslationFree([FromQuery] string query, [FromQuery] string fromLang, [FromQuery] string toLang)
        {
            using var httpClient = new HttpClient();
            // Replace with your actual endpoint
            string baseUrl = TranslateBaseUrls[getTranslationAPIIndex()];
            string url = $"{baseUrl}/api/v1/{Uri.EscapeDataString(fromLang)}/{Uri.EscapeDataString(toLang)}/{Uri.EscapeDataString(query)}";
            var response = await httpClient.GetAsync(url);
            response.EnsureSuccessStatusCode();
            string responseText = await response.Content.ReadAsStringAsync();
            TranslateResponse result = System.Text.Json.JsonSerializer.Deserialize<TranslateResponse>(responseText, new System.Text.Json.JsonSerializerOptions
            {
                PropertyNameCaseInsensitive = true,
                NumberHandling = System.Text.Json.Serialization.JsonNumberHandling.AllowReadingFromString
            });
            return result;
        }

        private static string GreekToEnglishTime(string greekWord)
        {
            var greekMap = new Dictionary<string, int>()
            {
                {"μιαμιση", 1},
                {"μιάμιση", 1},
                {"δυομιση", 2},
                {"δυόμιση", 2},
                {"τρειςμιση", 3},
                {"τρεισίμιση", 3},
                {"τεσσερισιμιση", 4},
                {"τεσσερισίμιση", 4},
                {"πεντεμιση", 5},
                {"πεντέμιση", 5},
                {"εξιμιση", 6},
                {"έξιμιση", 6},
                {"επταμιση", 7},
                {"εφτάμιση", 7},
                {"οχταμιση", 8},
                {"οκτώμιση", 8},
                {"εννιαμιση", 9},
                {"εννιάμιση", 9},
                {"δεκαμιση", 10},
                {"δεκάμιση", 10},
                {"εντεκαμιση", 11},
                {"έντεκαμιση", 11},
                {"δωδεκαμιση", 12},
                {"δωδεκάμιση", 12}
            };

            string lowerWord = greekWord.ToLower();
            foreach (string key in greekMap.Keys)
            {
                if (lowerWord.IndexOf(key)>-1)
                {
                    int hour = greekMap[key];
                    string replaceValue = $"{hour} {(hour == 1 ? "ώρα" : "ώρες")} και 30 λεπτά";
                    lowerWord = lowerWord.Replace(key, replaceValue);
                }
            }
            return lowerWord;
        }

        private static string prepareString(string input)
        {
            string s = GreekToEnglishTime(input);
            foreach (string k in TranslateResponseGoogle.QuuoteReplaceMents.Keys)
            {
                s = s.Replace(k, TranslateResponseGoogle.QuuoteReplaceMents[k]);
            }

            Dictionary<string,string> replaces = new Dictionary<string, string>
            {
                { " μισή", " 30 λεπτά" },
                { " μιση", " 30 λεπτά" },
            };

            foreach (string k in replaces.Keys)
            {
                s = s.Replace(k, replaces[k]);
            }
            return s;
        }

        [HttpPost]
        public async Task<RecognizeResponse> Date([FromBody] RecognizeRequest input)
        {
            string translation = "";
            string text = prepareString(input.input);
            RecognizeResponse recognizeResponse = new RecognizeResponse
            {
                InputText = input.input,
                FilterText = text,
                TranslationText = "",
                Matches = new List<ModelResult>()
            };
            TranslateResponseGoogle translateData = await GetTranslationGoogle(text, input.translateFrom, input.translateTo,input.key);
            if (translateData != null && (!string.IsNullOrEmpty(translateData.translation)))
            {
                recognizeResponse.TranslationText = translateData.translation;
                text = translateData.translation;
            }

            recognizeResponse.Matches = DateTimeRecognizer.RecognizeDateTime(text, input.matchLang, DateTimeOptions.CalendarMode);

            return recognizeResponse;
        }

        [HttpGet]
        public async Task<RecognizeResponse> GetDate([FromQuery] string input)
        {
            RecognizeRequest req = new RecognizeRequest
            {
                input = input,
                translateFrom = "el",
                translateTo = "en",
                matchLang = "el"
            };

            return await Date(req);
        }

        private async Task<TranslateResponseGoogle> GetTranslationGoogle(string query, string fromLang, string toLang,string key)
        {
            using var httpClient = new HttpClient();
            // Replace with your actual endpoint
            string baseUrl = "https://translation.googleapis.com/language/translate/v2";
            string url = $"{baseUrl}?q={Uri.EscapeDataString(query)}&target={Uri.EscapeDataString(toLang)}&key={Uri.EscapeDataString(key)}";
            var response = await httpClient.GetAsync(url);
            response.EnsureSuccessStatusCode();
            // Ensure UTF-8 encoding is used when reading the response
            var responseStream = await response.Content.ReadAsStreamAsync();
            using var reader = new System.IO.StreamReader(responseStream, System.Text.Encoding.UTF8);
            string responseText = await reader.ReadToEndAsync();
            TranslateResponseGoogle result = System.Text.Json.JsonSerializer.Deserialize<TranslateResponseGoogle>(responseText, new System.Text.Json.JsonSerializerOptions
            {
                PropertyNameCaseInsensitive = true,
                NumberHandling = System.Text.Json.Serialization.JsonNumberHandling.AllowReadingFromString
            });
            return result;
        }
    }
}
