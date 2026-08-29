<p align="center">
    <img src="docs/assets/laraiot-logo.png" alt="Logo LaraIoT" width="180">
</p>

<h1 align="center">LaraIoT</h1>

<p align="center">
    Un model Laravel demonstrativ și extensibil pentru monitorizarea și controlul echipamentelor IoT.
</p>

## Prezentare generală

LaraIoT oferă un model demonstrativ și extensibil pentru dezvoltarea unor aplicații web Laravel destinate monitorizării și controlului echipamentelor IoT. Pachetul integrează comunicarea MQTT, gestionarea dispozitivelor și topicurilor, persistarea stărilor, transmiterea comenzilor și două mecanisme alternative pentru actualizarea datelor: Polling și WebSockets.

Pachetul permite dezvoltatorilor, studenților și cercetătorilor să implementeze, testeze și compare cele două mecanisme de comunicare în același context aplicativ.

LaraIoT poate fi instalat în două moduri:

- **instalare independentă de frontend**, care furnizează componentele Laravel, integrarea MQTT, gestionarea dispozitivelor și serviciile de comunicare fără impunerea unei tehnologii frontend;
- **instalare opțională a interfeței Vue.js și Inertia**, care adaugă interfața web LaraIoT într-o aplicație ce utilizează un stack compatibil Vue.js, Inertia și Vite.

## Scopul și statutul proiectului

LaraIoT este un pachet creat în scop demonstrativ, educațional și de cercetare. El nu este destinat să fie o platformă IoT profesională, pregătită pentru producție.

Autentificarea, autorizarea, securizarea avansată, controlul accesului pentru mai mulți utilizatori și alte cerințe specifice mediilor de producție nu fac parte din scopul actual al proiectului.

Canalul WebSocket demonstrativ este public. El nu trebuie expus într-un mediu de producție sau într-o rețea neîncrezătoare fără implementarea unei arhitecturi de securitate adecvate.

## Avantajul utilizării LaraIoT

LaraIoT oferă o bază Laravel pregătită pentru prototipuri controlate, activități didactice și cercetare. Dezvoltatorul poate modela dispozitive, configura topicuri MQTT, persista stări, transmite comenzi și compara Polling cu WebSockets fără să implementeze de la zero aceste componente.

Backendul poate fi instalat independent de interfața Vue.js și Inertia, iar interfața opțională poate fi adăugată într-o aplicație compatibilă.

## Caracteristici

- gestionarea dispozitivelor fizice și logice;
- topicuri MQTT pentru stare și comandă;
- suport pentru payload-uri RAW și JSON;
- validarea topicurilor și testarea comenzilor;
- modul Polling;
- modul WebSocket cu Laravel Reverb;
- canal public `laraiot.devices` pentru demonstrații;
- interfață opțională Vue.js/Inertia;
- jurnalizarea activităților și setări ale aplicației.

## Cerințe

- PHP 8.3 sau o versiune ulterioară compatibilă;
- Laravel 11, 12 sau 13;
- Composer;
- broker MQTT pentru comunicarea cu echipamentele;
- Node.js 22 sau o versiune ulterioară, numai pentru interfața opțională;
- Laravel Reverb, numai pentru modul WebSocket.

## Instalare

```bash
composer require danpopa/laraiot
php artisan laraiot:install
php artisan migrate
```

Aceasta este instalarea independentă de frontend. Laravel Reverb este opțional; aplicația poate funcționa în modul Polling fără WebSocket.

## Instalarea interfeței Vue.js și Inertia

```bash
php artisan laraiot:install --ui
npm install
npm run build
```

Pentru suprascrierea fișierelor UI existente:

```bash
php artisan laraiot:install --ui --force
```

Aplicația gazdă trebuie să utilizeze un stack compatibil Vue.js, Inertia și Vite.

## Comenzi Artisan

Pornirea listener-ului MQTT:

```bash
php artisan laraiot:mqtt-listen
```

Transmiterea unei comenzi MQTT configurate:

```bash
php artisan laraiot:publish {topicId} {commandKey}
```

Opțional, poate fi indicat un identificator de client MQTT:

```bash
php artisan laraiot:publish {topicId} {commandKey} --client-id=client-name
```

## Polling și WebSockets

LaraIoT poate funcționa în modul Polling fără Laravel Reverb. Atunci când este selectat modul WebSocket și Reverb este disponibil, interfața se abonează la canalul public `laraiot.devices` și consumă evenimentele `logical-device.state-updated`.

Dacă serverul WebSocket nu este disponibil, interfața revine la modul Polling.

## Notă privind securitatea

LaraIoT trebuie utilizat pentru cercetare, instruire, prototipare și demonstrații controlate. Înaintea utilizării în producție, dezvoltatorul trebuie să implementeze și să verifice autentificarea, autorizarea, canalele WebSocket protejate, transportul criptat, securizarea brokerului MQTT, gestionarea secretelor, limitarea cererilor și monitorizarea.

## Testare

```bash
composer install
vendor/bin/pest
```

## Aplicație demonstrativă

Aplicația demonstrativă containerizată este disponibilă în repository-ul [`laraiot-app`](https://github.com/danielpopa26/laraiot-app).

## Autori

- **Daniel POPA**, student doctorand, Department of Electronics and Telecommunications, Faculty of Electrical Engineering and Information Technology, University of Oradea, Oradea, Romania.
- **Ioan BUCIU**, Professor, PhD, Habilitated Doctor, Department of Electronics and Telecommunications, Faculty of Electrical Engineering and Information Technology, University of Oradea, Oradea, Romania.

## Citare

Pentru citarea LaraIoT, utilizați metadatele din fișierul [`CITATION.cff`](CITATION.cff). DOI-ul va fi adăugat după arhivarea primei versiuni în Zenodo.

## Licență

LaraIoT este distribuit sub licența [MIT](LICENSE).