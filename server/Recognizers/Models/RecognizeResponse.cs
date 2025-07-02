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

        public long? BestMatchUnixTimestamp
        {
            get
            {
                if (BestMatch == null)
                    return null;

                return DateTimeToJavaTimeStamp((DateTime)BestMatch);
            }
        }

        public string FormattedBestMatch
        {
            get
            {
                if (BestMatch == null)
                    return null;
                CultureInfo frenchCulture = new CultureInfo("el-GR");
                return ((DateTime)BestMatch).ToString("dddd dd MMMM yyyy στις HH:mm", frenchCulture);
            }
        }
    }

    public class RecognizeRequest
    {
        public string input { get; set; }
        public string translateFrom{ get; set; } = "el";
        public string translateTo { get; set; } = "es";
        public string matchLang { get; set; } = "es-ES";
        public string key { get; set; } = "es-ES";
    }

}
