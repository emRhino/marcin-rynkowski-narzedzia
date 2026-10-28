# Moduł: Poprawki wyglądu

Drobne poprawki wyglądu Blocksy, każda z osobnym przełącznikiem w Narzędzia → Ustawienia → Poprawki wyglądu
(opcja `rynkowski_narzedzia_wyglad`). Nowe drobne poprawki dokładamy tutaj, nie jako osobne moduły.

- **Wymaga:** Blocksy.
- **Breadcrumb w linii** (`breadcrumb_w_linii`, domyślnie włączony): na telefonie (do 689.98 px, próg Blocksy)
  `.ct-breadcrumbs` zostaje w jednej linii, przewija się w bok bez paska, a `assets/okruszki.js` ustawia go
  na końcu (ostatni element widoczny), także po obrocie telefonu. Działa dla breadcrumbów Blocksy, Yoast
  i Rank Math (Blocksy owija je w `.ct-breadcrumbs`).
- **Zdjęcie produktu na całą szerokość** (`zdjecie_pelna_szerokosc`, domyślnie włączone): na telefonie, na
  stronie produktu główne zdjęcie (pojedyncze `figure.ct-media-container` albo widok karuzeli `.flexy`) ma
  `100vw` i ujemny margines, bez zaokrągleń. Galeria dostaje `overflow:visible` (Blocksy ją przycina), karuzela
  przycina slajdy sama (`.flexy-view` „boxed”). Miniatury (`.flexy-pills`) zostają w marginesach. Dotyczy
  domyślnej galerii Blocksy; inne układy galerii (np. z Blocksy Pro) — do sprawdzenia.
- **Dane:** tylko opcja ustawień; odinstalowanie ją usuwa.

| Plik | Co robi |
|---|---|
| `src/class-module.php` | definicja modułu, zakładka ustawień |
| `src/class-look-settings.php` | schemat przełączników |
| `src/class-breadcrumbs.php` | style breadcrumbu na telefonie |
| `src/class-product-image.php` | zdjęcie produktu na całą szerokość na telefonie |
| `assets/okruszki.js` | przewinięcie do końca |
