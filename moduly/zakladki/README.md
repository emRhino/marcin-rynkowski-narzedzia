# Moduł: Zakładki produktu

Dodatkowe opisy produktu z pełnym edytorem: **Pielęgnacja**, **Cechy produktu**, **Bezpieczeństwo produktu**.

- **Wymaga:** WooCommerce (działa z każdym motywem).
- **Edycja:** pole „Zakładki produktu” pod opisem w edycji produktu, edytor dla każdej zakładki
  (meta `_rynkowski_zakladki_<klucz>`, zapis przez `wp_kses_post`, pusta treść usuwa meta).
- **Sklep:** niepuste treści trafiają do zakładek WooCommerce (`woocommerce_product_tabs`) w stałej kolejności
  zaraz po Opisie (priorytety 11–13), klucze `rynkowski_<klucz>`. Treść formatowana `wc_format_content`
  i escapowana `wp_kses_post` (shortcode'y działają, ale np. `<iframe>` z ich wyniku zostanie wycięty).
- **Wygląd zostawiamy motywowi.** W Blocksy akordeon to *Customizer → Single Product → Product Tabs → Type 3*;
  „First Tab Expanded” i „Close Adjacent Tabs” ustawia się tam. Moduł nie ma własnych ustawień.
- **Dane:** odinstalowanie (za zgodą) usuwa treści zakładek ze wszystkich produktów.

| Plik | Co robi |
|---|---|
| `src/class-module.php` | definicja modułu, odinstalowanie |
| `src/class-tab-editor.php` | edytory w edycji produktu, zapis |
| `src/class-product-tabs.php` | lista zakładek, zakładki na karcie produktu |
