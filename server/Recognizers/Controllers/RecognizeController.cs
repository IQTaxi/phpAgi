using Microsoft.AspNetCore.Http;
using Microsoft.AspNetCore.Mvc;
using Microsoft.Extensions.Configuration;
using Microsoft.Recognizers.Text;
using Microsoft.Recognizers.Text.DateTime;
using Recognizers.Models;
using System.Net.Http;
using System.Runtime.Serialization.Json;
using System.Text.Json;
using System.Threading.Tasks;

namespace Recognizers.Controllers
{
    [Route("api/[controller]/[action]")]
    [ApiController]
    public class RecognizeController : ControllerBase
    {
        private readonly Microsoft.Extensions.Configuration.IConfiguration _configuration;

        public RecognizeController(Microsoft.Extensions.Configuration.IConfiguration configuration)
        {
            _configuration = configuration;
        }

        public static JsonSerializerOptions jsonSettings = new JsonSerializerOptions
        {
            PropertyNameCaseInsensitive = true,
            NumberHandling = System.Text.Json.Serialization.JsonNumberHandling.AllowReadingFromString
        };

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

        class TranslateResponseGoogleWrap
        {
            public TranslateResponseGoogle? data { get; set; }
            public bool isOK { get; set; }
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
                TranslationText = ""
            };
            TranslateResponseGoogleWrap translateData = await GetTranslationGoogle(text, input.translateFrom, input.translateTo,input.key);
            if (translateData?.data != null && (!string.IsNullOrEmpty(translateData?.data?.translation)))
            {
                recognizeResponse.TranslationText = translateData.data.translation;
                text = translateData.data.translation;
            }
            var forceLocale = _configuration["forceLocale"];
            recognizeResponse.Locale = !string.IsNullOrEmpty(forceLocale) ? forceLocale : input.translateFrom;
            recognizeResponse.setMatches(DateTimeRecognizer.RecognizeDateTime(text, input.matchLang, DateTimeOptions.CalendarMode));

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

        private async Task<TranslateResponseGoogleWrap> GetTranslationGoogle(string query, string fromLang, string toLang,string key)
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
            TranslateResponseGoogleWrap responseGoogleWrap = new TranslateResponseGoogleWrap
            {
                isOK = false
            };
            if (responseText != null)
            {
                responseGoogleWrap.isOK = true;
                responseGoogleWrap.data = JsonSerializer.Deserialize<TranslateResponseGoogle>(responseText, jsonSettings);
                return responseGoogleWrap;
            }

            return responseGoogleWrap;
        }
    }
}
