using Microsoft.Recognizers.Text;
using System.Globalization;

namespace Recognizers.Models
{
    public class RecognizeResponse
    {
        static System.DateTime LinuxDateTimeEpoch = new DateTime(1970, 1, 1, 0, 0, 0, 0, System.DateTimeKind.Utc);
        public static long DateTimeToJavaTimeStamp(DateTime date)
        {
            // Java timestamp is millisecods past epoch

            return (long)(date.ToUniversalTime() - LinuxDateTimeEpoch).TotalSeconds;
        }

        public string Locale { get; set; }  = "el-GR";
        public string InputText { get; set; }
        public string FilterText { get; set; }
        public string TranslationText { get; set; }
        public List<ModelResult> Matches { get; set; }        

        public DateTime? BestMatch { 
            get
            {
                if (Matches == null)
                    return null;
                DateTime? res = null;
                foreach (var match in Matches)
                {
                    if (match?.Resolution != null &&
                        match.Resolution.TryGetValue("values", out var valuesObj) &&
                        valuesObj is IEnumerable<object> valuesEnumerable)
                    {
                        foreach (var valueItem in valuesEnumerable)
                        {
                            if (valueItem is IDictionary<string, string> valueDict)
                            {
                                DateTime dateTime;
                                if (valueDict.TryGetValue("value", out var dateValueObj) &&
                                    dateValueObj is string dateValueStr &&
                                    DateTime.TryParse(dateValueStr, out dateTime))
                                {
                                    if ((res == null || dateTime > res) && dateTime>DateTime.Now && dateTime<DateTime.Now.AddMonths(6))
                                    {
                                        res = dateTime;
                                    }
                                }
                            }
                        }
                    }
                }
                return res;
            }
        }

        public DateTime[] BestMatches
        {
            get
            {
                if (Matches == null)
                    return null;
                List<DateTime> res = new List<DateTime>();
                foreach (var match in Matches)
                {
                    if (match?.Resolution != null &&
                        match.Resolution.TryGetValue("values", out var valuesObj) &&
                        valuesObj is IEnumerable<object> valuesEnumerable)
                    {
                        foreach (var valueItem in valuesEnumerable)
                        {
                            if (valueItem is IDictionary<string, string> valueDict)
                            {
                                DateTime dateTime;
                                string? objType = "";
                                if (!valueDict.TryGetValue("type", out objType) ||
                                    !(objType is string) ||
                                    (objType != "datetime"))
                                {
                                    continue;
                                }
                                if (valueDict.TryGetValue("value", out var dateValueObj) &&
                                    dateValueObj is string dateValueStr &&
                                    DateTime.TryParse(dateValueStr, out dateTime))
                                {
                                    if (dateTime > DateTime.Now && dateTime < DateTime.Now.AddMonths(6))
                                    {
                                        res.Add(dateTime);
                                    }
                                }
                            }
                        }
                    }
                }
                if (res.Count == 0 || res.Count>2)
                    return null;
                
                return res.ToArray();
            }
        }

        public long? BestMatchUnixTimestamp
        {
            get
            {
                if (BestMatch == null)
                    return null;

                return DateTimeToJavaTimeStamp((DateTime)BestMatch);
            }
        }
        public long[] BestMatchesUnixTimestamps
        {
            get
            {
                if (BestMatches == null)
                    return null;
                int l = BestMatches.Length;
                long[] res = new long[l];
                for (int i = 0; i < l; i++)
                {
                    res[i] = DateTimeToJavaTimeStamp(BestMatches[i]);
                }
                return res;
            }
        }

        public string FormattedBestMatch
        {
            get
            {
                if (BestMatch == null)
                    return null;
                CultureInfo frenchCulture = new CultureInfo(Locale);
                return ((DateTime)BestMatch).ToString($"dddd dd MMMM yyyy {at} HH:mm", frenchCulture);
            }
        }

        string at
        {
            get
            {
                switch (Locale)
                {
                    case "el":
                        return "στις";
                    case "en":
                        return "a\\t";
                    case "es":
                        return "a la\\s";
                    case "fr":
                        return "à";
                    case "de":
                        return "u\\m";
                    case "bg":
                        return "в";
                    default:
                        return "a\\t";
                }
            }
        }

        public string[] FormattedBestMatches
        {
            get
            {
                if (BestMatches == null)
                    return null;
                int c = BestMatches.Length;
                string[] res = new string[c];
                CultureInfo frenchCulture = new CultureInfo(Locale);
                for (int i = 0; i < c; i++)
                {
                    res[i] = ((DateTime)BestMatches[i]).ToString($"dddd dd MMMM yyyy {at} HH:mm", frenchCulture);
                }
                return res;
            }
        }
    }

    public class RecognizeRequest
    {
        public string input { get; set; }
        public string translateFrom{ get; set; } = "el";
        public string translateTo { get; set; } = "en";
        public string matchLang { get; set; } = "es-ES";
        public string key { get; set; } = "es-ES";
    }

}
