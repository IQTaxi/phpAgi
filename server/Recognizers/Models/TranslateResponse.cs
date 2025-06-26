using System;
using System.Collections.Generic;
using System.Text.Json.Serialization;

namespace Recognizers.Models
{
    

    public class TranslateResponse
    {
        public string translation { get; set; }
    }

    public class TranslateResponseGoogle
    {
        public static Dictionary<string, string> QuuoteReplaceMents = new Dictionary<string, string>
        {
            { "&#39;", "'" },
            { "&quot;", "\"" },
            { "&amp;", "&" },
            { "&lt;", "<" },
            { "&gt;", ">" }
        };
        public class TranslateResponseGoogleTranslation
        {
            public string translatedText { get; set; }
            public string detectedSourceLanguage { get; set; }
        }

        public class TranslateResponseGoogleData
        {
            public TranslateResponseGoogleTranslation[] translations { get; set; }
        }

        public TranslateResponseGoogleData data { get; set; }


        public string translation
        {
            get
            {
                if (data != null && data.translations != null && data.translations.Length > 0)
                {
                    string s = data.translations[0].translatedText;
                    foreach (string k in QuuoteReplaceMents.Keys)
                    {
                        s = s.Replace(k, QuuoteReplaceMents[k]);
                    }
                    return s;
                }
                return string.Empty;
            }
        }
    }
}