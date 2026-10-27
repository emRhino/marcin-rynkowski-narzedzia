# Moduł: Wstążki

Etykiety na zdjęciach produktów w stylu odznak Blocksy („SALE”, „SOLD OUT”).

- **Wymaga:** WooCommerce, Blocksy.
- **Panel:** WooCommerce → Wstążki (typ wpisu `rynkowski_wstazka`), zakładka „Wstążki” w edycji produktu (tylko odczyt).
- **Front:** filtry Blocksy `blocksy:woocommerce:product-card:badges` i `blocksy:woocommerce:single:after-sale-badge` (karta, galeria, quick view). Klasa `ct-woo-badge-rynkowski` dziedziczy styl odznak; kolory przez `--badge-text-color` / `--badge-background-color`. Kilka odznak → kontener `.rynkowski-wstazki-stos`.
- **Dane (meta wpisu):** `_rynkowski_wstazka_text`, `_text_color`, `_bg_color`, `_active` (`yes`/`no`), `_products`, `_categories`, `_tags` (tablice ID).
- **Wersja danych:** 1. Migracje w `src/class-module.php` → `migrations()`.
- **Cache:** po zmianie wstążki właściciel sklepu czyści cache (LiteSpeed) ręcznie.

| Plik | Co robi |
|---|---|
| `src/class-module.php` | definicja modułu, rejestracja części, odinstalowanie |
| `src/class-ribbons.php` | typ wpisu, odczyt danych, dopasowanie do produktu |
| `src/class-ribbon-admin.php` | ekran edycji, zapis, kolumny listy |
| `src/class-ribbon-product-tab.php` | zakładka w produkcie |
| `src/class-ribbon-frontend.php` | odznaki na froncie |
