# Prompt Finder Codebase

Ein WordPress-Theme und Plugin-System für die Prompt Finder Plattform - optimiert für Workflow-Management, Benutzer-Authentifizierung und Content-Gating.

## 🚀 Features

### Core Functionality
- **Workflow Management** - Vollständiges System für AI-Prompt-Workflows
- **User Authentication** - Login-basierte Zugriffskontrolle
- **Content Gating** - Flexible Freemium/Premium-Struktur
- **Rating System** - Benutzerbewertungen für Workflows
- **Favorites System** - Persönliche Workflow-Sammlung
- **Admin Dashboard** - Erweiterte Verwaltungsoberfläche

### Technical Features
- **Security** - Rate Limiting, CSRF-Schutz, Input-Validierung
- **Performance** - Optimierte Asset-Loading, intelligente Caching
- **Accessibility** - ARIA-Labels, Keyboard-Navigation
- **Responsive Design** - Mobile-optimierte Benutzeroberfläche

## 📁 Projektstruktur

```
prompt-finder-code/
├── assets/
│   ├── css/                    # Stylesheets
│   │   ├── pf-core.css         # Core Styles
│   │   ├── pf-landing.css      # Landing Page
│   │   ├── pf-workflows.css    # Workflow Pages
│   │   ├── pf-blog.css         # Blog Styles
│   │   └── pf-pricing.css      # Pricing Page
│   ├── js/                     # JavaScript
│   │   ├── pf-workflows.js     # Workflow Functionality
│   │   └── pf-pricing.js       # Pricing Logic
│   └── pf-config.json          # Configuration
├── functions.php               # Main Theme Functions
├── pf-core.php                 # Core Plugin
├── header.php                  # Theme Header
├── single-workflows.php        # Workflow Template
├── footer.php                  # Theme Footer
└── style.css                   # Theme Stylesheet
```

## ⚙️ Installation

### Voraussetzungen
- WordPress 5.0+
- PHP 7.4+
- MySQL 5.6+
- ACF Pro (Advanced Custom Fields)

### Setup
1. **Theme installieren**
   ```bash
   # Theme-Dateien in wp-content/themes/prompt-finder/ kopieren
   ```

2. **Plugin aktivieren**
   ```bash
   # pf-core.php als Plugin aktivieren
   ```

3. **ACF-Felder importieren**
   - Workflow-Felder aus ACF-JSON importieren
   - Custom Post Type "workflows" erstellen

4. **Konfiguration**
   - `assets/pf-config.json` anpassen
   - Theme-Optionen konfigurieren

## 🔧 Konfiguration

### pf-config.json
```json
{
  "version": "1.0.0",
  "feature_flags": {
    "howto_box": true,
    "next_panel": true,
    "rating": true,
    "share": true,
    "gating": true,
    "lock_badges": true,
    "value_panel": true
  },
  "workflow_defaults": {
    "access_mode": "half_locked",
    "free_step_limit": 1,
    "login_required": true
  },
  "copy": {
    "badge_free": "Free",
    "badge_limited": "Limited",
    "badge_pro_sub": "Members only"
  }
}
```

### Konstanten (functions.php)
```php
// Rating constants
define('PF_MIN_RATING', 1);
define('PF_MAX_RATING', 5);

// Rate limiting
define('PF_RATE_LIMIT_DURATION', 60); // seconds
define('PF_FAV_LIMIT_DURATION', 60); // seconds

// Cache
define('PF_CACHE_DURATION', 3600); // 1 hour
```

## 🛠️ Entwicklung

### Code-Standards
- **PHP**: PSR-12 Standard
- **JavaScript**: ES6+ mit Fallbacks
- **CSS**: BEM-Methodologie
- **Security**: WordPress Coding Standards

### Hilfsfunktionen

#### pf_load_config()
Lädt die JSON-Konfiguration mit Caching:
```php
$config = pf_load_config();
$flags = $config['feature_flags'] ?? [];
```

#### pf_get_user_plan()
Ermittelt den Benutzerplan:
```php
$plan = pf_get_user_plan(); // 'guest', 'free', 'pro'
```

#### pf_user_has_access()
Prüft Benutzerzugriff basierend auf Gating-Regeln:
```php
$has_access = pf_user_has_access([
    'login_required' => true,
    'required_cap' => 'pf_pro'
]);
```

#### pf_get_client_ip()
Sichere IP-Adresse-Erkennung mit Proxy-Support:
```php
$ip = pf_get_client_ip();
```

### AJAX-Handler

#### Rating System
```javascript
// Frontend
fetch('/wp-admin/admin-ajax.php', {
    method: 'POST',
    body: new FormData().append('action', 'pf_rate_workflow')
        .append('post_id', workflowId)
        .append('rating', rating)
        .append('nonce', nonce)
});
```

#### Favorites System
```javascript
// Toggle Favorite
fetch('/wp-admin/admin-ajax.php', {
    method: 'POST',
    body: new FormData().append('action', 'pf_toggle_favorite')
        .append('post_id', workflowId)
        .append('nonce', nonce)
});
```

## 🔒 Sicherheit

### Implementierte Schutzmaßnahmen
- **Rate Limiting**: 1 Request/Minute für Ratings, 5/Minute für Favorites
- **CSRF-Schutz**: Nonce-Validierung für alle AJAX-Requests
- **Input-Validierung**: Sanitization und Validierung aller Eingaben
- **IP-Erkennung**: Proxy-kompatible IP-Adresse-Erkennung
- **Error Handling**: Try-Catch-Blöcke mit Logging

### Rate Limiting
```php
// Beispiel: Rating Rate Limit
$rate_limit_key = 'pf_rate_limit_' . md5($user_ip);
if (get_transient($rate_limit_key)) {
    wp_send_json_error(['message' => 'Rate limit exceeded'], 429);
}
set_transient($rate_limit_key, 1, PF_RATE_LIMIT_DURATION);
```

## ⚡ Performance

### Asset-Optimierung
- **Intelligente Versionierung**: Production vs Development
- **Conditional Loading**: Assets nur bei Bedarf laden
- **Caching**: Statische Konfiguration wird gecacht

```php
// Asset-Versionierung
$version = wp_get_environment_type() === 'production' 
    ? wp_get_theme()->get('Version') 
    : filemtime($file_path);
```

### Database-Optimierung
- **Meta-Queries**: Optimierte ACF-Abfragen
- **Transients**: Caching für häufige Abfragen
- **Indexing**: Sortierbare Admin-Spalten

## 🎨 Frontend

### Workflow-Template (single-workflows.php)
- **Responsive Design**: Mobile-first Approach
- **Accessibility**: ARIA-Labels, Keyboard-Navigation
- **Interactive Elements**: Copy-to-Clipboard, Smooth Scrolling
- **Progressive Enhancement**: Graceful Degradation ohne JavaScript

### JavaScript-Features (pf-workflows.js)
- **Variable Substitution**: Live-Updates in Prompts
- **Cross-Step Variables**: Persistente Variablen zwischen Steps
- **Rating System**: AJAX-basierte Bewertungen
- **Favorites**: Toggle-Funktionalität
- **Smooth Scrolling**: Anker-Navigation zwischen Steps

## 📊 Admin-Features

### Erweiterte Admin-Spalten
- Version, Last Update, Access Mode
- Free Steps, Login Required, Tier
- Steps Count, Time Saved, Difficulty
- Use Case, Expected Outcome, Pain Points
- Rating (Durchschnitt und Anzahl)

### Sortierbare Spalten
```php
// Beispiel: Meta-Field Sorting
$map = [
    'pf_version' => ['key' => 'Version', 'type' => 'CHAR'],
    'pf_time_saved' => ['key' => 'time_saved_min', 'type' => 'NUMERIC']
];
```

## 🔧 Wartung

### Debugging
```php
// Error Logging aktivieren
error_log('[PF Error] ' . $error_message);

// Debug-Modus für Admins
if (current_user_can('manage_options')) {
    // Debug-Informationen anzeigen
}
```

### Monitoring
- **Error Logs**: Alle AJAX-Fehler werden geloggt
- **Rate Limiting**: Überwachung der Request-Limits
- **Performance**: Asset-Loading-Zeiten überwachen

## 🚀 Deployment

### Production-Setup
1. **Environment**: `WP_ENVIRONMENT_TYPE=production`
2. **Caching**: Object Cache aktivieren
3. **CDN**: Asset-Delivery optimieren
4. **Monitoring**: Error-Logging konfigurieren

### Performance-Checkliste
- [ ] Asset-Versionierung aktiviert
- [ ] Rate Limiting konfiguriert
- [ ] Error Handling getestet
- [ ] Security-Headers gesetzt
- [ ] Database-Indizes optimiert

## 📝 Changelog

### Version 1.0.0 (Aktuell)
- ✅ Doppelte Config-Ladung eliminiert
- ✅ Rate Limiting implementiert
- ✅ Asset-Loading optimiert
- ✅ Error Handling verbessert
- ✅ IP-Erkennung mit Proxy-Support
- ✅ Code-Qualität erhöht
- ✅ Sicherheit verstärkt

## 🤝 Contributing

### Code-Review-Prozess
1. **Security**: Rate Limiting und Validierung prüfen
2. **Performance**: Asset-Loading und Caching testen
3. **Standards**: WordPress Coding Standards einhalten
4. **Testing**: Frontend und Backend-Funktionalität testen

### Pull Request Checklist
- [ ] Keine Duplikation von Code
- [ ] Sicherheitsprüfungen bestanden
- [ ] Performance-Tests erfolgreich
- [ ] Dokumentation aktualisiert
- [ ] Linter-Fehler behoben

## 📞 Support

Bei Fragen oder Problemen:
- **Issues**: GitHub Issues verwenden
- **Documentation**: Diese README als Referenz
- **Code Review**: Sicherheits- und Performance-Checks

---

**Prompt Finder Codebase** - Optimiert für Performance, Sicherheit und Benutzerfreundlichkeit 🚀