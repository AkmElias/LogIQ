# LogIQ Translation Guide

## 🌍 Available Languages
- English (en_US) - Default
- Add your language here!

## 🛠️ Creating New Translations

### Prerequisites
1. Install WP-CLI (https://wp-cli.org/)
2. Install gettext tools (for PO/MO file handling)
   - On macOS: `brew install gettext`
   - On Ubuntu/Debian: `sudo apt-get install gettext`
   - On Windows: Download from https://mlocati.github.io/articles/gettext-iconv-windows.html

### Steps to Create a New Translation

1. **Generate POT file** (already included in `/languages/logiq.pot`)
```bash
wp i18n make-pot . languages/logiq.pot --exclude="node_modules,vendor,tests"
```

2. **Create PO file for your language**
```bash
# Replace xx_XX with your language code (e.g., es_ES for Spanish)
msginit --input=languages/logiq.pot --locale=xx_XX --output=languages/logiq-xx_XX.po
```

3. **Edit the PO file**
- Use a text editor or POEdit (https://poedit.net/)
- Translate all strings in msgstr fields
- Save the file

4. **Generate MO file**
```bash
msgfmt languages/logiq-xx_XX.po -o languages/logiq-xx_XX.mo
```

### Example: Creating Spanish Translation

```bash
# Create Spanish PO file
msginit --input=languages/logiq.pot --locale=es_ES --output=languages/logiq-es_ES.po

# Edit logiq-es_ES.po with translations
# Then generate MO file
msgfmt languages/logiq-es_ES.po -o languages/logiq-es_ES.mo
```

## 📝 Common Language Codes
- Spanish (es_ES)
- French (fr_FR)
- German (de_DE)
- Italian (it_IT)
- Dutch (nl_NL)
- Russian (ru_RU)
- Chinese (zh_CN)
- Japanese (ja)
- Korean (ko_KR)

## 🔄 Updating Translations

When new strings are added to the plugin:

1. **Update POT file**
```bash
wp i18n make-pot . languages/logiq.pot --exclude="node_modules,vendor,tests"
```

2. **Update existing PO files**
```bash
# Replace xx_XX with your language code
msgmerge --update languages/logiq-xx_XX.po languages/logiq.pot
```

3. **Translate new strings** in the PO file

4. **Regenerate MO file**
```bash
msgfmt languages/logiq-xx_XX.po -o languages/logiq-xx_XX.mo
```

## 🤝 Contributing Translations

1. Fork the repository
2. Create your translation files following the steps above
3. Submit a pull request with your translation files

## 📋 Translation Status
- [ ] English (en_US) - Default
- [ ] Spanish (es_ES)
- [ ] French (fr_FR)
- [ ] German (de_DE)
- Add your language here!

## 🔍 Testing Translations

1. Set WordPress language in Settings > General
2. Place PO/MO files in the `/languages` directory
3. Verify translations appear in the admin interface

## 📚 Resources
- [WordPress Translation Guide](https://developer.wordpress.org/plugins/internationalization/)
- [Poedit Translation Editor](https://poedit.net/)
- [WP-CLI i18n Commands](https://developer.wordpress.org/cli/commands/i18n/) 