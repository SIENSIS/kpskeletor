# Guia d'usuari (back-office)

**Idiomes:** [English](../user-guide.md) · Català · [Español](../es/user-guide.md)

Aquesta guia és per a les persones que fan servir el back-office: com iniciar la sessió, el teu
perfil i la seguretat del compte, el tauler, les notificacions, els fitxers, els continguts
(pàgines, menús, notícies), el calendari, les tasques, la teva activitat i els tokens personals de
l'API. La documentació tècnica per a desenvolupadors i administradors és en anglès a `docs/`.

## Abans de començar

- **El que veus depèn dels teus permisos.** Els administradors donen permisos als grups (per
  exemple `admin`, `editor`, `user`) i cada usuari pertany a un o més grups. El menú només mostra
  les pantalles que pots obrir, i els botons que no pots fer servir no hi apareixen. Si et falta
  alguna pantalla d'aquesta guia, demana-ho a un administrador. A més, alguns mòduls es poden
  desactivar (el mòdul de tasques ve desactivat per defecte).
- **Adreces.** Els camins com `/backend/profile` són relatius a l'adreça del teu lloc (per exemple
  `https://example.org/backend/profile`). El back-office comença a `/backend`.
- **On és cada cosa.** El back-office té tres llocs amb entrades de menú:
  - la **barra lateral**, a l'esquerra: les pantalles principals (Tauler, Notícies, Pàgines,
    Calendari, Menús, Fitxers, Tasques…) i, sota l'encapçalament **Configuració**, les pantalles
    d'administració;
  - el **menú d'usuari**, a dalt a la dreta (el teu nom): El meu perfil, Notificacions, La meva
    activitat, Les meves sessions, Els meus tokens d’API, Subscripció al calendari i **Tanca la
    sessió**;
  - la **campaneta**, a la barra superior: les darreres notificacions.
- **Idioma.** El back-office es mostra en l'idioma del teu perfil (català, castellà o anglès). Els
  rètols que cita aquesta guia són els catalans.

## Iniciar la sessió

### Entrar

Obre `/login` (a la web pública, **Entra** al final de la barra de menú), escriu l'**Adreça
electrònica** i la **Contrasenya** i prem **Entra**. Amb la sessió iniciada, el mateix lloc de la
barra diu **Administració** (o **El meu compte** si no tens cap pantalla de l'administració).

- El botó de l'ull al costat de cada camp de contrasenya mostra el que has escrit (**Mostra la
  contrasenya**) i el torna a amagar; la contrasenya sempre torna a quedar amagada en enviar el
  formulari.

- **Recorda'm en aquest dispositiu** (si apareix) manté la sessió oberta en aquest navegador durant
  uns quants dies (14 per defecte). No ho facis servir en ordinadors compartits.
- Si els administradors han activat el captcha, apareix un camp **Verificació** (una operació
  aritmètica dibuixada en una imatge): escriu-ne el resultat. **Una altra imatge** en dibuixa una
  de nova.
- Després de diversos intents fallits l'accés queda bloquejat uns minuts. Els missatges d'error
  no diuen mai si s'ha equivocat l'adreça o la contrasenya, i és expressament així.
- Si el compte té la verificació en dos passos, tot seguit apareix la pantalla **Codi de
  verificació** (vegeu [Verificació en dos passos](#verificació-en-dos-passos)).
- Si un administrador t'ha demanat que canviïs la contrasenya, primer et porta al formulari de la
  contrasenya («Has de canviar la contrasenya abans de continuar»).
- Quan entres des d'un dispositiu o navegador que el lloc no coneix, reps un correu d'avís d'inici
  de sessió, **Inici de sessió des d'un dispositiu nou**. Si no has estat tu, segueix **Revisa les
  meves sessions** al correu, tanca aquella sessió i canvia la contrasenya.

### Entrar amb un enllaç per correu

Si la pàgina d'inici de sessió mostra **Entra amb un enllaç per correu**, pots entrar sense
escriure la contrasenya: obre-ho (`/login/magic-link`), escriu la teva adreça i prem **Envia
l'enllaç**. Rebràs un correu amb un enllaç d'un sol ús que caduca al cap de poca estona. Si el
compte té la verificació en dos passos, després de l'enllaç també et demanarà el codi.

### Contrasenya oblidada

Prem **Has oblidat la contrasenya?** a la pàgina d'inici de sessió (`/reset-password`), escriu
la teva adreça i prem **Envia l'enllaç**. El correu porta un enllaç d'un sol ús per triar una
**Contrasenya nova** (escriu-la dues vegades i prem **Desa la contrasenya**). Per privadesa, la
pàgina sempre diu que s'ha enviat el correu «si hi ha un compte amb aquesta adreça»: mira
també la carpeta de correu brossa.

### Bloqueig i caducitat de la sessió

- Si els administradors han fixat un temps d'inactivitat, després d'una estona sense fer res
  apareix la pantalla **Sessió bloquejada**: escriu la contrasenya i prem **Desbloqueja** (o
  **No ets tu? Tanca la sessió**).
- Una sessió també s'acaba un temps fix després d'entrar (12 hores per defecte), encara que la
  facis servir: només cal tornar a entrar.
- Si veus que la sessió «s'ha tancat des d'un altre dispositiu», vol dir que tu (o un
  administrador) l'has tancada des d'un altre lloc, o que s'ha canviat la contrasenya.

### Tancar la sessió

Obre el menú d'usuari i tria **Tanca la sessió**. Si obres `/logout`, abans de tancar et pregunta
«Vols tancar la sessió en aquest dispositiu?».

## El meu perfil

Menú d'usuari › **El meu perfil** (`/backend/profile`).

La targeta **Dades personals** conté:

| Camp | Per a què serveix |
|---|---|
| **Avatar** | **Puja un fitxer**: JPG, PNG o WebP, fins a 2 MB. La imatge es torna a codificar sense metadades i només la veuen els usuaris que han iniciat la sessió. Desa el perfil per aplicar el canvi. |
| **Nom** | El nom que veuen els altres. |
| **Adreça electrònica** | L'adreça amb què entres. Per canviar-la cal la **Contrasenya actual**. |
| **Idioma** | Català, castellà o anglès: l'idioma del back-office i dels correus que reps. |
| **Zona horària** | Les dates i hores es mostren en aquesta zona horària. |
| **Mode de color** | Automàtic (el del sistema), Clar o Fosc. El botó de mode de color de la barra superior també el canvia. |
| **Data de naixement** i **Comparteix el meu aniversari al calendari** | Opcional. Només es comparteixen el dia i el mes, i només amb els grups que permetin els administradors. |
| **Contrasenya actual** | Només cal si canvies l'adreça electrònica. |

Prem **Desa** per guardar els canvis. La targeta **Compte**, a la dreta, mostra els teus grups i
els botons **Canvia la contrasenya**, **Verificació en dos passos** i **Les meves sessions**.

### Canviar la contrasenya

Perfil › **Canvia la contrasenya** (`/backend/profile/password`): escriu la contrasenya actual,
la **Contrasenya nova** i **Repeteix la contrasenya nova**. El formulari indica la llargària
mínima (8 caràcters per defecte; una frase llarga és més segura i fàcil de recordar). Es
rebutgen les contrasenyes massa habituals o filtrades i les que es basen en la teva adreça. En
canviar la contrasenya **es tanquen les altres sessions obertes i es revoquen els tokens de les
aplicacions**.

## Verificació en dos passos

Perfil › **Verificació en dos passos** (`/backend/profile/two-factor`). Amb aquesta opció, per
entrar necessites la contrasenya **i** un codi de 6 xifres. Alguns grups l'exigeixen: aleshores
la pàgina diu que el teu grup requereix la verificació en dos passos i l'has de configurar per
continuar; en pots canviar el mètode però no la pots desactivar.

Els canvis d'aquesta pàgina et demanen que [confirmis la identitat](#confirmar-la-identitat)
(la contrasenya i, si ja fas servir la verificació, el codi actual).

### Triar el mètode

La targeta **Mètode** n'ofereix dos; prem **Utilitza aquest mètode** en el que vulguis:

- **Aplicació d'autenticació** (recomanat): Google Authenticator, Microsoft Authenticator,
  Aegis, 1Password… La pàgina **Configura una aplicació d'autenticació** explica els passos:
  1. instal·la una aplicació d'autenticació al mòbil;
  2. escaneja el codi QR o escriu la clau a mà («Clau (si no podeu escanejar el codi)»);
  3. escriu el codi de 6 xifres que mostra l'aplicació i prem **Activa**.
- **Codi per correu**: a cada inici de sessió reps un codi de 6 xifres per correu (caduca al cap
  de 10 minuts). A la pantalla de verificació, **Envia un codi nou** n'envia un altre.

Si diu «No disponible: el servidor no té clau de xifratge», els administradors han de configurar
el servidor abans que es pugui fer servir l'aplicació.

### Codis de recuperació

Quan actives la verificació en dos passos reps **10 codis de recuperació**. Només es mostren
**una vegada**: copia'ls i desa'ls en un lloc segur (un gestor de contrasenyes, o impresos a
casa). Cada codi et permet entrar una vegada si perds l'accés al mètode: a la pantalla de
verificació tria **Utilitza un codi de recuperació**. La targeta **Codis de recuperació** mostra
quants codis et queden per fer servir; **Genera codis nous** te'n dona un joc nou i invalida
l'anterior.

### Si perds el mòbil

1. Entra amb la contrasenya i, a la pantalla **Codi de verificació**, tria **Utilitza un codi de
   recuperació**.
2. Ves a Perfil › Verificació en dos passos i torna a configurar l'aplicació al mòbil nou (o passa
   a **Codi per correu**); després prem **Genera codis nous**.
3. Si ja no et queden codis de recuperació, demana-ho a un administrador: a Usuaris pot fer
   **Restablir 2FA** al teu compte. Rebràs una notificació i, si el teu grup exigeix la
   verificació, la tornaràs a configurar en el proper inici de sessió.

Si algú més pot tenir el teu mòbil o els teus codis, canvia també la contrasenya i tanca les
altres sessions.

### Desactivar-la

**Desactiva la verificació en dos passos** (només si el teu grup no l'exigeix) deixa el compte
protegit només amb la contrasenya.

## Les meves sessions

Menú d'usuari › **Les meves sessions** (`/backend/profile/sessions`).

- **Sessions obertes** mostra els dispositius on tens la sessió oberta: **Dispositiu**, **Adreça
  IP**, **Inici** i **Darrera activitat**. L'actual porta la marca **Aquesta sessió**;
  **Recordada** indica les sessions obertes amb «Recorda'm».
- **Tanca** en una fila tanca aquella sessió a l'instant; **Tanca totes les altres sessions**
  les tanca totes menys l'actual. Per tancar l'actual, fes servir **Tanca la sessió** del menú
  d'usuari.
- La targeta **Tokens de l'API** mostra els tokens de les teves aplicacions (**Aplicació**) i els
  personals (**Personal**) amb el darrer ús; **Revoca** atura un token a l'instant.

Si no reconeixes alguna sessió, tanca-la i canvia la contrasenya.

## Confirmar la identitat

Les accions delicades (per exemple, canviar la verificació en dos passos, crear un token
personal de l'API o l'adreça del canal del calendari) mostren la pantalla **Confirmeu la vostra
identitat**: escriu la **Contrasenya** i, si fas servir la verificació en dos passos, el codi
actual (o un codi de recuperació), i prem **Confirma**. Amb el mètode per correu, **Envieu-me un
codi per correu** t'envia el codi. La identitat queda confirmada uns minuts (15 per defecte), de
manera que les accions relacionades no la tornen a demanar; si l'acció no s'ha completat,
repeteix-la. Massa intents fallits tanquen la sessió.

## Tauler

Barra lateral › **Tauler** (`/backend`), la primera pantalla després d'entrar.

- Mostra **ginys** (targetes com Propers esdeveniments, Avisos, Darreres notícies, Enllaços
  ràpids, Les meves tasques pendents…). Els administradors decideixen quins ginys té cada grup
  (Configuració › **Ginys del tauler**); només veus un giny si el té el teu grup **i** tens el
  permís que necessita.
- **Personalitza** et permet organitzar el teu tauler: arrossega els ginys per la nansa (o fes
  servir les fletxes del teclat a sobre) per reordenar-los, canvia'n la mida (Petit, Mitjà, Gran,
  Amplada completa) o amaga'ls. Els canvis es desen a l'instant; prem **Fet** per acabar. Els
  ginys amagats surten a **Ginys amagats**, des d'on els pots tornar a mostrar.
- **Restaura la disposició del grup** oblida el teu ordre, les mides i els ginys amagats.
- Sense JavaScript, `/backend/dashboard/customize` ofereix les mateixes opcions en una taula
  (**Posició**, **Mida**, **Visible** i després **Desa**).

Si un giny falla, només aquella targeta mostra l'error; la resta del tauler continua funcionant.

## Notificacions

- **La campaneta** de la barra superior mostra quantes notificacions tens per llegir i obre les
  darreres. En fer-hi clic s'obre la notificació i queda marcada com a llegida. La campaneta
  també ofereix **Marca-ho tot com a llegit**, **Mostra-les totes** i **Preferències**.
- **La llista:** menú d'usuari › **Notificacions** (`/backend/notifications`) mostra totes les
  notificacions amb la data i si són **Llegida** o **No llegida**. **Marca com a llegida**
  funciona amb una notificació o amb les seleccionades, i **Marca-ho tot com a llegit**, amb
  totes.
- **Preferències:** **Preferències de notificacions** (des de la llista o la campaneta;
  `/backend/profile/notifications`) et permet triar, per a cada tipus de notificació, si la reps
  **A l'aplicació**, **Per correu**, de les dues maneres o de cap. Les marcades com a
  **Obligatòria** no es poden desactivar. Quan el lloc té les notificacions push activades, una
  tercera columna, **Als meus dispositius**, tria quines notificacions arriben als teus telèfons i
  ordinadors.

### Notificacions als meus dispositius (push)

Si els administradors ho activen, pots rebre les notificacions com a notificacions del sistema en
un telèfon o un ordinador, fins i tot amb el lloc tancat (gratuït, sense instal·lar cap
aplicació):

1. Obre **Preferències de notificacions** al navegador d'aquest dispositiu.
2. Fes clic a **Activa les notificacions en aquest dispositiu** i permet les notificacions quan
   el navegador ho demani.
3. Fes servir **Envia una prova** per comprovar-ho (arriba en menys d'un minut).

La llista **Els vostres dispositius** mostra cada navegador que rep les notificacions («Aquest
dispositiu» marca el que fas servir): pots **Canviar el nom**, enviar una prova o **Eliminar-lo**.
Quan tanques la sessió en un dispositiu que rep notificacions, se't pregunta si vols mantenir-les
o eliminar el dispositiu.

- **iPhone i iPad** (iOS/iPadOS 16.4 o posterior): primer afegeix el lloc a la pantalla d'inici
  (Compartir › Afegir a la pantalla d'inici), obre'l des d'allà i activa les notificacions.
- Si el navegador ha **bloquejat** les notificacions, permet-les a la configuració del lloc del
  navegador i torna a carregar la pàgina.
- Els administradors poden triar que les notificacions push només diguin «Teniu una notificació
  nova» (les pantalles de bloqueig les mostren); llavors el text es llegeix al lloc.

Exemples de notificacions: un avís nou a les notícies, el recordatori d'un esdeveniment que has
demanat, un restabliment de la verificació en dos passos per part d'un administrador o una tasca
programada que ha fallat (per als administradors).

## Fitxers

Barra lateral › **Fitxers** (`/backend/files`), per als usuaris amb accés al gestor de fitxers.
La resta només pugen fitxers des dels camps dels formularis (vegeu [Fitxers als
formularis](#fitxers-als-formularis-el-selector-de-fitxers)).

### Espais

L'arbre **Carpetes** de l'esquerra mostra els espais que pots obrir:

| Espai | Contingut |
|---|---|
| **Els meus fitxers** | El teu espai personal (privat). Només el veus tu. S'ofereix als grups que triïn els administradors (per defecte `admin` i `editor`). |
| **Compartit (públic)** | Carpetes compartides amb fitxers que tenen una adreça pública (qualsevol persona amb l'adreça els pot obrir: fes-les servir per a imatges i documents del web). |
| **Compartit (privat)** | Carpetes compartides amb fitxers que només se serveixen a les persones que permeten els permisos de la carpeta. |

### Treballar amb fitxers

- **Puja** (o arrossega els fitxers a la llista: «Deixa anar els fitxers aquí per pujar-los») puja
  a la **Carpeta actual**. El quadre **Pujades** mostra el progrés de cada fitxer. Els tipus
  permesos i la mida màxima els fixen els administradors; els fitxers que no compleixen es
  rebutgen amb un missatge.
- Cerca per nom, text alternatiu o etiqueta; filtra per **Tipus**, dates (**Des de**, **Fins a**)
  i **Etiqueta**; **Ordena per** Nom, Més recents o Més grans; canvia la **Vista** entre
  **Llista** i **Quadrícula**.
- En fer clic en un fitxer s'obren els **Detalls del fitxer**: previsualització, mida,
  dimensions, **Descarrega**, **Previsualitza**, **Copia l’URL pública** (fitxers públics) o
  **Copia l’URL** (fitxers privats), **Mou** a una altra carpeta, i els camps **Nom**,
  **Etiquetes** (separades per comes) i **Text alternatiu** (descriu les imatges per a qui no les
  pot veure).
- **Mou a la paperera** elimina un fitxer. La vista **Paperera** mostra els fitxers eliminats:
  **Restaura** en recupera un i **Elimina definitivament** l'esborra per sempre (no es pot
  desfer).

### Carpetes i permisos

Segons els teus permisos pots crear (**Carpeta nova**), **Canvia el nom** i **Elimina la
carpeta** (només carpetes buides: sense fitxers, sense fitxers a la paperera i sense
subcarpetes). El botó **Permisos** d'una carpeta compartida dona drets (**Veure**, **Pujar**,
**Editar**, **Eliminar**, **Gestionar**) a un **Grup**, a un **Usuari** o a **Tots els usuaris amb
sessió**; les subcarpetes els hereten si no en defineixen de propis.

### Quotes

Un espai o una carpeta poden tenir un límit de mida. El gestor mostra «*X* de *Y* utilitzats»;
els fitxers de la paperera compten fins que s'eliminen definitivament. Si una pujada el
superaria, es rebutja («Se superaria la quota de la carpeta»): buida la paperera o elimina
fitxers. L'espai personal té 100 MB per defecte.

### Fitxers als formularis (el selector de fitxers)

Els camps que admeten un fitxer (un avatar, una imatge de portada, adjunts…) mostren **Puja un
fitxer** i, si el camp ho permet, **Tria un fitxer**, que obre un diàleg amb les carpetes que pots
fer servir per a aquell camp (**Carpeta superior** per pujar un nivell, **Tria** per confirmar).
El fitxer actual es mostra amb un botó per treure'l. El camp comprova el tipus i la mida abans de
pujar-lo. El canvi només es guarda quan deses el formulari.

A l'editor de text enriquit, **Insereix una imatge** obre el mateix diàleg (**Puja una imatge** o
tria'n una), demana el **Text alternatiu (descriu la imatge)** i la insereix. Deixa el text
alternatiu buit només si la imatge és decorativa.

## Pàgines

Barra lateral › **Pàgines** (`/backend/pages`): les pàgines del web públic.

- El formulari té una pestanya per idioma (el català és obligatori): **Títol**, **Àlies** (la part
  de l'adreça; si és buit es fa a partir del títol), **Resum**, **Text**, els camps per als
  cercadors (**Metatítol**, **Metadescripció**) i la **Imatge destacada**. Els textos sense traduir
  es mostren en català.
- **Pàgina pare** col·loca la pàgina dins d'una secció: la seva adreça comença amb la del pare.
- **Visibilitat**: **Pública**, **Usuaris identificats** o **Grups** (només els membres dels
  grups triats).
- **Estat**: **Esborrany**, **Programada** o **Publicada**. **Publica** publica la pàgina (amb
  una **Data de publicació** futura queda programada); **Despublica** la torna a esborrany.
- **Previsualitza** mostra com quedarà la pàgina, encara que sigui un esborrany. **Revisions**
  mostra les versions desades amb els canvis, i **Restaura** en recupera una (se'n guarda una
  revisió nova). **Duplica** en fa una còpia com a esborrany.

Publicar sol requerir un permís específic; qui no pot publicar desa esborranys.

### Pàgines fetes amb seccions

Una pàgina pot mostrar el seu text (**Disposició: Article**) o fer-se amb **seccions**
(**Disposició: Seccions**): bàners, enllaços ràpids, targetes, imatge i text, crides a l'acció i
blocs de text, una sota l'altra, a tota l'amplada del web. No cal escriure codi.

1. A la llista de pàgines, obriu el menú de la fila › **Seccions**.
2. Si l'avís diu que la pàgina mostra el seu text, premeu **Fes servir seccions** (el text es
   conserva).
3. **Afegeix una secció**, trieu-ne el tipus a la galeria, ompliu el formulari i **Desa**. Amb els
   botons d'idioma de dalt del formulari escriviu els textos de cada idioma; les imatges necessiten
   un **text alternatiu** (què mostra la imatge) si no les marqueu com a **decoratives**.
4. Canvieu l'ordre arrossegant una secció per la nansa (o enfoqueu la nansa i feu servir les
   fletxes amunt i avall) i premeu **Desa l'ordre**. **Amaga** conserva una secció sense
   mostrar-la; **Duplica** i **Elimina** fan el que diuen.
5. **Previsualitza** mostra la pàgina tal com és ara. Cada canvi queda a les **Revisions** de la
   pàgina, i restaurar una revisió en recupera les seccions.

## Menús

Barra lateral › **Menús** (`/backend/menus/public`): els menús del web públic.

- Cada menú té una **Ubicació** (on el mostra el tema). **Elements** n'obre els elements.
- **Afegeix un element** (o **Afegeix un element a dins** d'un altre) enllaça una pàgina, una
  adreça, una ruta o un enllaç que ofereixen els mòduls (una categoria de notícies, el
  calendari…), amb la seva visibilitat (per exemple, només usuaris identificats) i si s'obre en
  una pestanya nova.
- Ordena i niua els elements arrossegant la nansa, amb les fletxes del teclat a sobre o amb els
  botons, i després desa l'ordre. Els elements que apunten a una cosa que ja no existeix porten
  la marca **Destí no disponible**.

La part **Configuració** de la barra lateral està agrupada: **Usuaris i accés** (usuaris, grups,
permisos), **Seguretat** (configuració de seguretat, estat de seguretat, registre d'activitat),
**API** (tokens, documentació), **Personalització** (ginys del tauler, menú del back-office,
mòduls) i **Sistema** (configuració, tasques programades, còpies de seguretat, actualitzacions).
Cada grup només apareix si pots obrir alguna de les seves entrades.

Els administradors també poden personalitzar el menú del back-office (**Personalització** › **Menú
del back-office**); cada entrada manté el seu permís sigui on sigui, i els grups es poden
reanomenar, moure o amagar com qualsevol entrada.

## Notícies

Barra lateral › **Notícies**:

- **Notícies** (`/backend/news`): el lector. Mostra els articles i avisos que pots llegir; filtra
  per **Tipus**, **Categoria** i **Només no llegides** i prem **Filtra**. Les no llegides porten
  la marca **Nova** i les fixades surten primer. Obre'n una per llegir-la i prem **Marca com a
  llegida**.
- **Totes les publicacions** (`/backend/news/posts`) i **Les meves publicacions**
  (`/backend/news/mine`): per escriure i gestionar publicacions (la segona només mostra les
  teves). Una publicació és un **Article** o un **Avís** (els avisos tenen prioritat i es poden
  fixar; els avisos privats notifiquen els lectors quan es publiquen). **Visibilitat**:
  **Pública**, **Privada** (usuaris identificats) o **Grups**. L'estat passa d'**Esborrany** a
  **Programada** / **Publicada** i després a **Caducada** (passada la **Data de caducitat**).
  **Publica** i **Despublica** el canvien; **Lectors** mostra qui ha llegit una publicació (amb el
  permís).
- **Categories** (`/backend/news/categories`): les categories de notícies.

Un avís amb **Data de l'esdeveniment** també apareix al calendari, per al mateix públic.

## Calendari i esdeveniments

### El calendari

Barra lateral › **Calendari** (`/backend/calendar`) mostra tots els esdeveniments que pots veure.

- Vistes: mes, setmana, dia i llista (al mòbil es mostra primer la llista). Fes servir les
  fletxes i el botó d'avui per moure't.
- **Calendaris**: a més d'**Esdeveniments**, els mòduls hi poden afegir els seus calendaris (per
  exemple els avisos de les notícies, els aniversaris o el venciment de les tasques). Marca els que
  vulguis i prem **Aplica**; el botó de l'ull mostra **Només** aquell calendari i **Mostra-ho
  tot** els torna a mostrar tots.
- **Categoria** filtra els esdeveniments per categoria (**Totes les categories** per treure el
  filtre).
- Fes clic en un esdeveniment per veure'n els detalls (quan és, si es repeteix, **Lloc**,
  **Categoria**, **Organitzador**, **Enllaç**). Des d'allà:
  - **Avisa’m** + **Desa** t'envia una notificació una estona abans que comenci (l'opció sense
    recordatori el treu);
  - **Afegeix al meu calendari (.ics)** descarrega l'esdeveniment per a una altra aplicació de
    calendari;
  - **Edita** (amb el permís), o l'opció d'editar-lo al mòdul que el gestiona.

El calendari públic del web és a `/calendar` (només esdeveniments públics).

### Gestionar esdeveniments

Calendari › **Esdeveniments** (`/backend/events`) i **Categories** (`/backend/events/categories`),
amb els permisos. Un esdeveniment té **Títol**, **Inici**, **Final** (opcional), **Tot el dia**,
**Lloc**, **Categoria**, **Estat** (**Confirmat**, **Provisional**, **Cancel·lat**) i
**Visibilitat**: **Públic** (també al web), **Privat** (qualsevol usuari identificat) o
**Restringit** (només els **Grups** i **Usuaris** triats). La **Repetició** (Diària, Setmanal,
Mensual, Anual), amb **Cada**, **Els dies**, **Fins al** o **Nombre de vegades** i les **Dates
excloses**, crea esdeveniments periòdics. **Duplica** copia un
esdeveniment.

### Subscriure-s'hi des d'una altra aplicació de calendari

Menú d'usuari › **Subscripció al calendari** (`/backend/calendar/subscription`) et dona una
adreça privada (un canal iCal) a la qual es poden **subscriure** les aplicacions de calendari.
El canal mostra tots els esdeveniments que pots veure aquí i s'actualitza sol; és només de
lectura (els canvis es fan al back-office).

1. Prem **Crea l'adreça del meu canal** i [confirma la identitat](#confirmar-la-identitat).
2. La pàgina mostra **L'adreça del teu canal del calendari** (té la forma
   `https://example.org/ical/user/<secret>.ics`). **Copia**-la ara: només es mostra un cop,
   perquè es desa xifrada. Qualsevol persona amb aquesta adreça pot llegir el teu calendari; no la
   comparteixis.
3. Afegeix-la a la teva aplicació com a calendari **subscrit per URL** (no com a fitxer
   importat):

| Aplicació | Passos |
|---|---|
| **Google Calendar** | Des d'un ordinador: Altres calendaris › + › Des de l'URL, enganxa l'adreça i tria «Afegeix el calendari». Després també apareix al mòbil. |
| **Outlook** | Afegeix un calendari › Subscriu-te des del web, enganxa l'adreça, posa-hi un nom i importa'l. |
| **Calendari d'Apple (macOS, iPhone)** | Fes servir **Obre a la meva aplicació de calendari**, o Arxiu › Nova subscripció a un calendari (Mac) / Configuració › Calendari › Comptes › Afegeix un calendari subscrit (iPhone). |
| **Synology Calendar** | Calendar › Crea › Subscriu-te a un calendari, enganxa l'adreça i tria cada quant s'actualitza. |
| **Thunderbird** | Calendari nou › A la xarxa › iCalendar (ICS), enganxa l'adreça. |

Cada aplicació decideix cada quant llegeix el canal (normalment entre 15 minuts i un dia): un
canvi pot trigar aquest temps a aparèixer.

- **Què inclou el canal:** marca alguns **Calendaris** o **Categories** i prem **Desa** per
  limitar el canal; deixa-ho tot sense marcar per incloure tots els teus esdeveniments.
- **Regenera l'adreça** (restablir el canal) crea una adreça nova i fa que l'antiga deixi de
  funcionar de seguida a totes les aplicacions que la fan servir: fes-ho si has perdut l'adreça o
  creus que algú altre la té, i després actualitza les aplicacions. **Revoca** atura el canal
  sense crear-ne cap de nou.
- La targeta mostra l'**Estat**, quan es va crear (**Creat**) i la **Darrera lectura d'una
  aplicació**.
- Quan el lloc publica el calendari públic, la pàgina també mostra l'adreça del canal
  d'**esdeveniments públics** (`/ical/public/all.ics`), que no necessita sessió i es pot
  compartir.

## Tasques

Barra lateral › **Tasques** (`/backend/tasks`), quan el mòdul està activat.

- El **tauler** mostra les tasques a les columnes **Per fer**, **En curs** i **Fetes**. Arrossega
  una targeta a una altra columna, o tria'n l'estat a **Mou a** i prem **Mou**. Segons els teus
  permisos veus totes les tasques o només les que tens assignades. **Tasca nova** en crea una; pots
  filtrar per projecte.
- **Totes les tasques** (`/backend/tasks/tasks`) és la llista completa: **Projecte**,
  **Prioritat**, **Etiquetes**, **Data de venciment**, **Persones assignades**, adjunts i **Completar** per marcar-ne
  una com a feta.
- **Projectes** i **Sol·licituds** (les que arriben del formulari públic; **Acceptar** crea una tasca i
  **Rebutjar** tanca la sol·licitud), amb els permisos.
- El giny del tauler **Les meves tasques pendents** mostra les teves.

## La meva activitat

Menú d'usuari › **La meva activitat** (`/backend/activity/mine`) mostra el que has fet i els
esdeveniments de seguretat del teu compte: inicis de sessió (també els fallits), canvis de
contrasenya i d'adreça, canvis de la verificació en dos passos, sessions tancades, tokens creats
o revocats, registres creats, modificats o eliminats… Cada entrada té la **Data**, el tipus
(**Activitat** o **Seguretat**), l'**Acció** i el **Resum**; obre-la per veure'n els canvis. El
registre no es pot modificar. Si hi veus alguna cosa que no has fet tu, canvia la contrasenya,
tanca les altres sessions i avisa un administrador.

## Els meus tokens d’API

Menú d'usuari › **Els meus tokens d’API** (`/backend/profile/api-tokens`), per als usuaris que
en poden crear (per defecte, els grups `editor` i `admin`). Un token personal permet que un
programa o una integració facin servir l'API en nom teu; mai no pot fer més del que pots fer tu.

1. A **Token nou**, posa-li un **Nom** (on el faràs servir), tria **Caduca d’aquí a** (7, 30, 90 o
   365 dies) i **Què pot fer**: **Tot el que puc fer jo** o **Només aquests permisos**.
2. Prem **Crear el token** i [confirma la identitat](#confirmar-la-identitat).
3. **El teu token nou** només es mostra una vegada: copia'l ara.

**Els meus tokens** mostra els teus tokens amb el darrer ús; **Revocar** n'atura un a l'instant
(els programes que el fan servir deixen de funcionar). Els permisos que perdis més endavant
també els perdrà el token. Els tokens també surten, i es poden revocar, a [Les meves
sessions](#les-meves-sessions).

## Referència ràpida

| Pantalla | On és | Adreça |
|---|---|---|
| Inici de sessió | — | `/login` |
| Tauler | Barra lateral | `/backend` |
| El meu perfil | Menú d'usuari | `/backend/profile` |
| Canvia la contrasenya | El meu perfil | `/backend/profile/password` |
| Verificació en dos passos | El meu perfil | `/backend/profile/two-factor` |
| Les meves sessions | Menú d'usuari | `/backend/profile/sessions` |
| Notificacions | Menú d'usuari, campaneta | `/backend/notifications` |
| Preferències de notificacions | Notificacions, campaneta | `/backend/profile/notifications` |
| La meva activitat | Menú d'usuari | `/backend/activity/mine` |
| Els meus tokens d’API | Menú d'usuari | `/backend/profile/api-tokens` |
| Subscripció al calendari | Menú d'usuari | `/backend/calendar/subscription` |
| Fitxers | Barra lateral | `/backend/files` |
| Pàgines | Barra lateral | `/backend/pages` |
| Menús | Barra lateral | `/backend/menus/public` |
| Notícies | Barra lateral | `/backend/news` |
| Calendari | Barra lateral | `/backend/calendar` |
| Tasques | Barra lateral | `/backend/tasks` |
