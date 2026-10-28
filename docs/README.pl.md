# InPost Shopware Plugin — bezpłatna wtyczka InPost do Shopware 6

[English](../README.md) · **Polski** · [Deutsch](README.de.md)

Połącz sklep Shopware 6 z InPost ShipX. Klienci wybierają Paczkomat lub punkt odbioru podczas składania zamówienia, a Ty tworzysz przesyłki, pobierasz etykiety PDF i sprawdzasz ich status w panelu administracyjnym Shopware.

Wtyczka została przygotowana przez [Clueware](https://clueware.de) i jest dostępna bezpłatnie na [licencji MIT](../LICENSE).

**[Pobierz najnowsze wydanie](https://github.com/pgawdzik/inpost-shopware-plugin/releases/latest)** · [Zgłoś problem](https://github.com/pgawdzik/inpost-shopware-plugin/issues)

## Co potrafi wtyczka?

- Wybór Paczkomatu lub punktu odbioru w checkout przez InPost GeoWidget.
- Dostawa do punktu odbioru oraz dostawa kurierem InPost.
- Tworzenie pojedynczych przesyłek bezpośrednio z widoku zamówienia.
- Pobieranie etykiet PDF w formacie A6 lub A4.
- Numer śledzenia, odświeżanie statusu i anulowanie przesyłki.
- Obsługa pobrania, automatyczne obliczanie wagi i propozycja gabarytu.
- Ręczne wymiary paczki dla przesyłek kurierskich.
- Konfiguracja osobno dla każdego kanału sprzedaży.
- Tryb sandbox i produkcyjny, test połączenia ShipX oraz dziennik operacji.
- Osobne uprawnienia administracyjne do obsługi przesyłek.
- Tłumaczenia interfejsu na angielski, niemiecki i polski.

## Wybór punktu w checkout

Wybrany punkt odbioru jest sprawdzany po stronie serwera i zapisywany z zamówieniem. Klient może ponownie otworzyć mapę i zmienić punkt przed złożeniem zamówienia.

![Wtyczka InPost do Shopware 6: wybór Paczkomatu i punktu odbioru w checkout](images/inpost-shopware-checkout.jpg)

## Wymagania

| Element | Wymaganie |
|---|---|
| Shopware | Własna instalacja Shopware **6.7.x** z modułem Storefront |
| PHP | Wersja zgodna z wymaganiami używanej instalacji Shopware |
| Metody dostawy | Istniejąca metoda dostawy z przypisanym czasem dostawy — potrzebna podczas instalacji |
| ShipX | Własny token API i ID organizacji InPost ShipX |
| GeoWidget | Publiczny token przypisany do domeny sklepu — dla wyboru punktów |

Integracja przesyłek korzysta z InPost ShipX w Polsce. Lista krajów widocznych w GeoWidget steruje mapą; dostępność usług przesyłek zależy od konta ShipX.

## Instalacja z pliku ZIP

1. Otwórz [najnowsze wydanie](https://github.com/pgawdzik/inpost-shopware-plugin/releases/latest).
2. Pobierz paczkę instalacyjną **`CluewareInpostShipping-2.0.15.zip`** z sekcji **Assets**.
3. W panelu Shopware przejdź do **Rozszerzenia → Moje rozszerzenia** i wybierz przesłanie rozszerzenia.
4. Wgraj ZIP, zainstaluj i aktywuj **InPost Shipping**.
5. Otwórz konfigurację wtyczki i wykonaj poniższe kroki.

Paczka instalacyjna zawiera pliki wtyczki wraz z gotowymi zasobami administracji i Storefrontu. Wybierz ją zamiast automatycznego archiwum **Source code** generowanego przez GitHub.

### Instalacja ze źródeł

Umieść pliki repozytorium w katalogu `custom/plugins/CluewareInpostShipping` swojej instalacji Shopware. Następnie uruchom z głównego katalogu sklepu:

```bash
bin/console plugin:refresh
bin/console plugin:install --activate CluewareInpostShipping
bin/console cache:clear
```

Uwzględnij przebudowę motywu w procesie wdrożenia sklepu, aby załadować rozszerzenia Storefrontu.

## Konfiguracja

![Konfiguracja integracji InPost ShipX w panelu administracyjnym Shopware 6](images/inpost-shopware-configuration.jpg)

1. Wybierz kanał sprzedaży, który chcesz skonfigurować.
2. Ustaw środowisko **Sandbox** albo **Produkcja**.
3. Wprowadź **prywatny token API ShipX** i **ID organizacji**.
4. Dla Paczkomatów i punktów odbioru dodaj **publiczny token GeoWidget** przypisany do domeny sklepu.
5. Wybierz kraje widoczne na mapie, usługi InPost, format etykiety i domyślne ustawienia paczki.
6. Zapisz ustawienia i uruchom test połączenia ShipX. Sprawdza on dostęp do organizacji ShipX we wszystkich skonfigurowanych kanałach; wybór punktów GeoWidget sprawdź osobno w checkout.
7. Przed włączeniem integracji sprawdź ceny, czasy dostawy i warunki dostępności metod InPost. Początkowa cena dostawy wynosi **0**, więc ustaw stawki właściwe dla swojego sklepu. Następnie włącz integrację i zapisz konfigurację.

Podczas instalacji powstają metody **InPost Paczkomat** i **InPost Kurier**. Pozostają niedostępne, dopóki dla kanału sprzedaży nie zostaną zapisane wymagane dane konfiguracji. Zapis konfiguracji automatycznie synchronizuje przypisanie tych metod do kanałów sprzedaży.

Przesyłki InPost wymagają numeru telefonu odbiorcy. Wtyczka domyślnie pokazuje natywne pole telefonu w adresie Shopware dla aktywnej integracji; obowiązek jego wypełnienia dotyczy zamówień z dostawą InPost.

## Obsługa zamówienia

Otwórz zamówienie z metodą dostawy InPost i znajdź kartę przesyłki. Sprawdź wagę, gabaryt lub wymiary, opcjonalną kwotę pobrania i format etykiety, a następnie utwórz przesyłkę. Po jej utworzeniu możesz pobrać etykietę, otworzyć śledzenie, odświeżyć status lub anulować przesyłkę, jeżeli pozwala na to jej status w InPost.

## Zakres i dane

Wtyczka obsługuje pojedyncze przesyłki. Nie zawiera operacji masowych, obsługi zwrotów, wielu paczek w jednej przesyłce ani zamawiania podjazdu kuriera. Bezpłatność dotyczy wtyczki; usługi przewoźnika rozliczasz z InPost.

Prywatny token ShipX pozostaje po stronie serwera. Utworzenie przesyłki przekazuje do InPost dane odbiorcy i przesyłki potrzebne do realizacji dostawy. GeoWidget jest ładowany po otwarciu wyboru punktu i korzysta z publicznego tokenu.

## Pomoc i licencja

Problemy i propozycje zmian zgłaszaj przez [GitHub Issues](https://github.com/pgawdzik/inpost-shopware-plugin/issues). Przy zgłoszeniu podaj wersję Shopware i wtyczki oraz kroki odtworzenia problemu; pomiń tokeny API i dane klientów.

Kod jest dostępny na [licencji MIT](../LICENSE). Projekt rozwija [Clueware](https://clueware.de).
