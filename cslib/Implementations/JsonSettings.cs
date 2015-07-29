using System.Collections.Generic;
using Newtonsoft.Json;

namespace cslib
{
    public class JsonSettings : ISettings
    {
        private Dictionary<string, dynamic> _settings;
        
        public JsonSettings(string json)
        {
            this._settings = JsonConvert.DeserializeObject<Dictionary<string, dynamic>>(json);
        }

        public dynamic Get()
        {
            return this._settings;
        }
    }
}

