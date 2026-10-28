# Moduł: Liczniki promocji

Tekst promocji (np. „-12% Z KODEM: FLASH”) z odliczaniem do jej końca, pod ceną na stronie produktu.

- **Wymaga:** WooCommerce.
- **Panel:** Narzędzia → Liczniki promocji (typ wpisu `rynkowski_licznik`): tekst, koniec (data i godzina w strefie sklepu), aktywny, przypisanie do produktów / kategorii / tagów.
- **Wygląd (wspólny dla wszystkich):** Narzędzia → Ustawienia → Liczniki promocji (opcja `rynkowski_narzedzia_liczniki`): kolory tekstu, cyfr, ramki i wypełnienia, pogrubienie i wielkość tekstu oraz cyfr, padding pola, odstęp między polami. Schemat w `src/class-counter-settings.php`.
- **Front:** `woocommerce_single_product_summary` priorytet 11 (zaraz po cenie; Blocksy przenosi 11–20 na swój hook „po cenie”). Gdy produkt obejmuje kilka liczników, pokazuje się ten, który kończy się najwcześniej.
- **Odliczanie i cache:** `assets/liczniki.js` liczy w przeglądarce od `data-koniec` (czas Unix); po końcu chowa licznik — także na stronie podanej z cache LiteSpeed. Serwer nie wypisuje licznika po czasie.
- **Dostępność:** cyfry `aria-hidden`, dla czytników jedno zdanie z datą końca (`screen-reader-text`).
- **Prawo:** licznik ma odliczać do prawdziwego końca promocji (UOKiK: fałszywa presja czasu). Przy „-X%” sprawdź obowiązek Omnibus (najniższa cena z 30 dni).
- **Dane (meta wpisu):** `_rynkowski_licznik_text`, `_end` (czas Unix UTC), `_active`, `_products`, `_categories`, `_tags`.
- **Wersja danych:** 1.

| Plik | Co robi |
|---|---|
| `src/class-module.php` | definicja modułu, zakładka ustawień, odinstalowanie |
| `src/class-counters.php` | typ wpisu, odczyt, wybór licznika dla produktu |
| `src/class-counter-admin.php` | ekran edycji, zapis (data w strefie sklepu → UTC), kolumny |
| `src/class-counter-settings.php` | schemat wyglądu |
| `src/class-counter-frontend.php` | licznik na stronie produktu, style z ustawień |
| `assets/liczniki.js` | odliczanie w przeglądarce |
