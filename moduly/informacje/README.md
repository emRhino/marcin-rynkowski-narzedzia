# Moduł: Informacje o zakupie

Sekcja pod przyciskiem „Dodaj do koszyka”: wiersze z ikoną i podpisem (czas wysyłki, dostawa, zwroty, płatności…).

- **Wymaga:** WooCommerce. Miejsce: `woocommerce_single_product_summary` priorytet 35 (Blocksy przenosi 30–40
  za warstwę „Add to Cart”, więc sekcja stoi przed akordeonem w podsumowaniu).
- **Wiersze** (typ wpisu `rynkowski_informacja`, Narzędzia → Informacje o zakupie): ikona z biblioteki mediów
  (`_rynkowski_informacja_ikona`, tylko obrazek), tekst domyślny (`_tekst`), aktywność (`_aktywny`, nowy = aktywny),
  kolejność w natywnym polu „Kolejność” (`menu_order`).
- **Podpis:** edytor tylko tekstowy (quicktags) z przyciskami b, link, „wyróżnij” (`<mark>`, kolor z ustawień)
  i „dopisek” (`<small>`, mniejsza druga linia). Własne przyciski tylko dla edytorów modułu (8. argument
  `QTags.addButton`). Dozwolony HTML: `Info_Rows::allowed_html()` (strong, b, em, mark, small, br, a[href]);
  czyszczenie przy zapisie (`clean_text`) i ponowne `wp_kses` przy wypisaniu.
- **Produkt:** okno „Informacje o zakupie” z polem dla każdego aktywnego wiersza; puste = tekst domyślny
  (meta produktu `_rynkowski_informacje_teksty`: ID wiersza => tekst). Zapis dotyka tylko aktywnych wierszy,
  teksty wyłączonych zostają. Wiersz bez tekstu (ani własnego, ani domyślnego) się nie pokazuje.
- **Ustawienia** (`rynkowski_narzedzia_informacje`): pokazuj sekcję, kolor wyróżnienia, kolor ramki
  (puste = kolory motywu Blocksy), rozmiar ikony, odstęp w wierszu, odstęp pod sekcją.
- **Dane:** odinstalowanie usuwa wiersze, teksty produktów i ustawienia.

| Plik | Co robi |
|---|---|
| `src/class-module.php` | definicja modułu, zakładka ustawień, odinstalowanie |
| `src/class-info-rows.php` | typ wpisu, odczyt wierszy i tekstów produktu, dozwolony HTML |
| `src/class-info-row-admin.php` | ekran wiersza, lista, wspólny edytor podpisu |
| `src/class-info-product-box.php` | okno w edycji produktu, zapis |
| `src/class-info-frontend.php` | sekcja na karcie produktu, CSS |
| `src/class-info-settings.php` | schemat ustawień |
| `assets/informacje-admin.js` | wybór ikony z biblioteki mediów |
