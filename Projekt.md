# Ich arbeite an einem Fork von https://github.com/steeven-th/SuluTailwindThemeBundle.

Bitte implementiere folgende zwei Erweiterungen:

## 1. **Grid-Block (neu)**
- Neuer Block-Typ der X Kind-Blöcke nebeneinander in einem CSS Grid anzeigt
- Im Sulu Admin konfigurierbar: Spaltenanzahl (2, 3, 4)
- Responsive: auf Mobile untereinander, ab md: nebeneinander
- Tailwind CSS Grid Klassen verwenden (grid, grid-cols-2, md:grid-cols-3 etc.)

## 2. **Custom CSS & ID Feld (alle bestehenden Blöcke)**
- Jeder bestehende Block bekommt zwei neue optionale Felder:
- `custom_id`: HTML id-Attribut am <section> Element
- `custom_classes`: Freitext für zusätzliche CSS Klassen am <section> Element
- Die Felder sollen im Sulu Admin unter "Einstellungen" des jeweiligen Blocks erscheinen

Bitte halte dich an die bestehenden Konventionen des Bundles (Sulu XML Forms, Twig Templates, PHP Entity falls nötig).