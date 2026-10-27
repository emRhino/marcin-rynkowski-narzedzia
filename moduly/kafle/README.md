# Moduł: Kafle promocyjne

Grafika, wideo albo tekst na kolorowym tle w miejscu produktu na liście sklepu, kategorii i tagu.

- **Wymaga:** WooCommerce.
- **Panel:** WooCommerce → Kafle promocyjne (typ wpisu `rynkowski_kafel`).
- **Front:** akcja `woocommerce_shop_loop` (archiwum, bez shortcode'ów). Pozycja = numer komórki siatki; kolizje stają jedna za drugą. Kafel z pozycją dalszą niż liczba produktów na stronie się nie pokazuje. Kategoria bez podkategorii.
- **Wideo:** autoplay bez dźwięku, przycisk pauzy (WCAG 2.2.2), „ogranicz ruch” = start zatrzymany (`assets/kafle.js`).
- **Dane (meta wpisu):** `_rynkowski_kafel_type` (`image`/`video`/`text`), `_link`, `_active`, `_on_shop`, `_categories`, `_tags`, `_position`, `_first_page_only`, `_image_id`, `_video_id`, `_poster_id`, `_heading`, `_description`, `_button`, `_bg_color`, `_text_color`.
- **Wersja danych:** 1. Migracje w `src/class-module.php` → `migrations()`.
- **Świadomie odłożone:** wyrównanie paginacji (niepełny ostatni wiersz), treść z edytora bloków.

| Plik | Co robi |
|---|---|
| `src/class-module.php` | definicja modułu, rejestracja części, odinstalowanie |
| `src/class-tiles.php` | typ wpisu, odczyt danych |
| `src/class-tile-admin.php` | ekran edycji, biblioteka mediów, zapis, kolumny |
| `src/class-tile-frontend.php` | wstawianie kafli do pętli produktów |
| `assets/kafle-admin.js` | pola zależne od typu, wybór mediów |
| `assets/kafle.js` | pauza wideo na froncie |
