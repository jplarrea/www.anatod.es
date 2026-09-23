# Anatod · Web estática por dominio

Las mismas páginas sirven para España, México, Argentina y la web global. El navegador detecta el dominio y carga assets/config/site.json. No necesita PHP, base de datos, compilación ni CDN.

## El archivo que debes editar

**assets/config/site.json** es la configuración de toda la web:

- **domains**: ajustes particulares de cada dominio.
- **defaults**: textos, imágenes, enlaces y opciones comunes.
- **pricing**: una única lista de precios y monedas para todos los países.
- **defaultDomain**: versión utilizada en un dominio no reconocido; actualmente anatod.com.
- **localDomain**: versión inicial de la prueba en localhost; actualmente anatod.es.

Se combinan los valores comunes con los del dominio. Dentro de cada dominio solo hace falta indicar lo que cambia. Borrar una personalización recupera el valor común; un texto vacío ("") se mantiene vacío.

Los precios se leen exclusivamente del bloque global pricing. Un bloque pricing dentro de defaults o domains se ignora; no cambia los importes ni las monedas disponibles.

| Dominio, con o sin www | Contacto | Moneda mostrada |
| --- | --- | --- |
| anatod.es | +34 683 22 78 40 | EUR |
| anatod.com.mx | +52 56 5400 0698 | USD |
| anatod.com.ar | +54 9 291 5373762 | USD |
| anatod.com | +1 404 496 5122 | USD |

El teléfono y la moneda se determinan por la configuración del dominio, no por la ubicación del visitante ni por el país escrito en el formulario.

## Cambiar un texto solo en España

Dentro de domains → anatod.es → texts, añade o edita una clave existente. Por ejemplo:

    "texts": {
      "home.hero.eyebrow": "ANATOD · ESPAÑA",
      "home.hero.description": "Tu nuevo texto para operadores de España.",
      "home.meta.title": "Anatod España · Software de gestión para ISP"
    }

Conserva las demás claves de ese bloque. Los dominios que no personalicen ese texto utilizarán defaults → texts.

Para cambiarlo en todos los países, edita el texto en defaults → texts. Si algún dominio ya lo personaliza, edita o elimina también esa personalización.

Los textos son texto plano: no admiten etiquetas HTML. Los títulos con saltos o palabras en color se dividen en varias claves para conservar el diseño. Ejemplo:

- home.hero.titleLead: Software de
- home.hero.titleMiddle: gestión
- home.hero.titleAccent: para ISP.

## Cambiar el teléfono y WhatsApp

En domains → dominio → contact:

    "contact": {
      "number": "34683227840",
      "display": "+34 683 22 78 40",
      "label": "Atención comercial · España"
    }

- number: prefijo internacional y número, solo dígitos, sin "+" ni espacios. Se utiliza para llamar y para ambos enlaces de WhatsApp.
- display: número tal como se muestra en pantalla.
- label: texto debajo del teléfono.
- message: mensaje inicial de WhatsApp. Se hereda de defaults → contact → message; también puedes añadirlo a un dominio.

El formulario prepara el mensaje localmente. El visitante lo revisa y lo envía desde WhatsApp. El campo País aporta contexto; cambiarlo no modifica el destinatario. No hay envío de email ni servidor de formularios.

## Qué puedes personalizar

Cada bloque de domains admite las mismas propiedades de defaults:

| Propiedad | Uso |
| --- | --- |
| texts | Textos de home, software y Sobre nosotros, navegación, formulario, títulos, descripciones, textos alternativos y accesibilidad. |
| contact | Número, formato visible, etiqueta y mensaje de WhatsApp. |
| links | Enlaces de navegación, documentación, privacidad, eventos y llamadas a la acción. |
| images | Logo, favicon, equipo, participación en eventos y asociaciones. |
| currency | Única moneda mostrada en los precios de ese dominio. |
| locale | Formato regional de números; por ejemplo es-ES, es-MX o es-AR. |
| lang | Idioma de la página; por ejemplo es-ES. |
| country | Valor inicial del campo País; no cambia el destino del contacto. |
| messages | Textos dinámicos: validación, tema, menú y etiquetas del mensaje de WhatsApp. |

Claves frecuentes de texts:

- home.hero.description
- home.hero.primaryCta
- home.hero.secondaryCta
- software.hero.description
- about.*: contenido de la página Sobre nosotros
- home.meta.title / home.meta.description
- software.meta.title / software.meta.description
- about.meta.title / about.meta.description
- common.locale: Open Graph, por ejemplo es_MX.
- form.nameLabel / form.namePlaceholder
- form.companyLabel / form.companyPlaceholder
- form.emailLabel / form.emailPlaceholder
- form.prepare / form.ready
- contact.whatsappLabel
- footer.description / footer.tagline

Los textos de Sobre nosotros se agrupan en claves `about.*` dentro de `defaults.texts`, incluidas las descripciones de sus fotografías y sus metadatos. Puedes personalizar esas mismas claves dentro de `domains → dominio → texts`. Los enlaces `company` y `about` apuntan a `about.html`; la imagen de participación en eventos se configura en `images.companyEvent`.

Todos los textos editoriales visibles tienen su clave en defaults.texts. Puedes buscar una frase en el JSON o consultar el atributo data-copy de su elemento en el HTML.

Las rutas de enlaces e imágenes se resuelven respecto a la carpeta de la web. Un enlace que empieza por "#" conserva la página actual. Puedes utilizar una ruta local o una URL HTTPS.

Ejemplo de imagen específica de España:

    "images": {
      "team": "assets/img/equipo-espana.jpg"
    }

Sube también el archivo de imagen. Para cambiar su descripción, personaliza la clave de texto alternativo de esa imagen.

## Cambiar precios

Edita pricing → rates en la raíz de site.json para modificar la tarifa de todos los países. Cada entrada contiene un importe numérico por moneda; los decimales se escriben con punto, sin símbolo monetario.

Ejemplo del primer tramo, dentro del bloque global pricing:

    "rates": {
      "tier5000": {
        "EUR": 1350,
        "USD": 1500
      }
    }

Conserva las demás entradas de rates al editar. tier5000 controla el precio inicial y el primer tramo. El resto utiliza tier10000, tier20000, tier40000, tier60000, tier80000 y tier100000.

Hay una sola oferta, con todos los módulos y desarrollos futuros incluidos. Esta es la tarifa mensual publicada, igual para España, México, Argentina y la web global:

| Tramo publicado | USD / mes | EUR / mes | Clave |
| --- | ---: | ---: | --- |
| 1–5.000 unidades | 1.500 | 1.350 | tier5000 |
| 5.000–10.000 unidades | 3.000 | 2.700 | tier10000 |
| 10.000–20.000 unidades | 5.000 | 4.500 | tier20000 |
| 20.000–40.000 unidades | 8.000 | 7.000 | tier40000 |
| 40.000–60.000 unidades | 11.000 | 10.000 | tier60000 |
| 60.000–80.000 unidades | 13.000 | 11.500 | tier80000 |
| 80.000–100.000 unidades | 15.000 | 13.500 | tier100000 |
| Más de 100.000 unidades | A medida | A medida | Texto de consulta |

Cada conexión de Internet equivale a 1 unidad. Cada servicio de telefonía, TV o suscripción equivale a 0,25 unidades. La tabla conserva los límites tal como se publican en la fuente; no se calcula automáticamente un tramo, porque los extremos publicados se solapan.

Cada dominio muestra exclusivamente la moneda indicada en su propiedad currency. Los importes son tarifas publicadas, no conversiones con tipos de cambio. La propiedad locale cambia el formato del importe, sin cambiar su moneda.

Para cambiar la moneda de una web, edita domains → dominio → currency. Por ejemplo, dentro de anatod.es:

    "currency": "EUR"

Puedes indicar otra moneda que ya tenga tarifas en el bloque global pricing. Para añadir una nueva, añádela a pricing.currencies y completa su importe en todas las entradas de pricing.rates antes de asignarla a un dominio. El visitante no elige la moneda y los parámetros de la URL no la modifican.

Los tramos y explicaciones también son textos editables. La estructura de las tablas se conserva en HTML; añadir o quitar filas/secciones implica editar el HTML además del JSON.

Los importes y las condiciones se verificaron el 23 de septiembre de 2026 en https://www.anatod.com/ispkeeper/precios/. El servicio es mensual, en la nube y sin tiempo mínimo de contratación. Los precios no incluyen impuestos. La puesta en marcha tiene un coste por única vez, acordado antes de contratar, que incluye capacitación remota o presencial, migración de datos y configuración inicial; ese importe no es reembolsable.

La página de software también recoge soporte humano en tiempo real para urgencias, alojamiento regional en AWS, usuarios ilimitados, múltiples copias de seguridad redundantes diarias, CDN y actualizaciones automáticas. Conserva la explicación sobre servicios e instancias independientes de AWS y la distribución mundial de archivos. Estos textos se editan en defaults → texts, junto con los tramos, las referencias de unidades y las condiciones comerciales.

## Probar antes de publicar

Para que el navegador lea el JSON hay que abrir la web mediante HTTP, no haciendo doble clic en el HTML. Sigue siendo una web completamente estática.

Desde esta carpeta:

    python3 -m http.server 8088 --bind 127.0.0.1

En la prueba local puedes elegir una versión:

- http://127.0.0.1:8088/index.html?site=anatod.es
- http://127.0.0.1:8088/index.html?site=anatod.com.mx
- http://127.0.0.1:8088/index.html?site=anatod.com.ar
- http://127.0.0.1:8088/index.html?site=anatod.com

La navegación conserva esa selección. La prueba local muestra el contacto y la moneda configurados para el dominio elegido. El parámetro site solo funciona en localhost/127.0.0.1/IPv6 local. En un dominio público siempre manda el hostname; los parámetros site o currency no cambian el contacto ni la moneda.

Los teléfonos y precios permanecen ocultos hasta cargar una configuración válida. Si el JSON falta o contiene errores, aparece una opción para volver a intentar y el formulario no prepara mensajes. El tema y la navegación básica siguen funcionando. Sin JavaScript se conserva el contenido de respaldo, sin contactos ni precios específicos.

El navegador vuelve a solicitar el JSON al cargar cada página. Si el alojamiento utiliza caché propia o CDN, publica el JSON actualizado e invalida esa caché cuando corresponda.

## Archivos y publicación

- index.html: home breve y presentación de la empresa.
- software.html: funciones, integraciones, tecnología, precios y contacto.
- about.html: historia, equipo, forma de trabajar y participación de Anatod en el sector.
- assets/config/site.json: configuración editable.
- assets/js/site-config.js: lectura, selección y aplicación de configuración.
- assets/js/site.js: menú, temas, precios y formulario.
- assets/css/site.css: diseño responsive, claro y oscuro.
- assets/lib/bootstrap.css: Bootstrap 5.3.8, licencia MIT.
- assets/fonts/: Manrope y licencia SIL OFL.
- tests/site-config.test.cjs: comprobaciones de dominios, configuración, referencias y navegación de las tres páginas.

Puedes servir esta misma carpeta desde los cuatro dominios o publicar los mismos archivos en sus alojamientos. El JSON se lee desde el dominio de cada web; si mantienes copias separadas, sincroniza los cambios del archivo en todas ellas.

No se modificaron DNS, alojamientos ni sitios de producción. Las tres páginas conservan noindex, nofollow por ser una prueba. Para publicar definitivamente, retira esa etiqueta y revisa tarifas, condiciones y metadatos. Los metadatos regionales se aplican con JavaScript: los rastreadores que no lo ejecuten verán el texto genérico del HTML.

La configuración es un archivo público. Solo la preferencia de tema se guarda en el navegador; no se guardan datos de contacto ni se incluyen analítica o cookies de seguimiento.

## Comprobar cambios

    node --test tests/site-config.test.cjs
    node --check assets/js/site.js
    node --check assets/js/site-config.js

## Fuentes del contenido inicial

- https://www.anatod.com/about/
- https://www.anatod.com/ispkeeper/
- https://www.anatod.com/ispkeeper/precios/
- https://www.anatod.com/ispkeeper/fibra-optica/
- https://www.anatod.com/ispkeeper/telefonia-movil-fija-ip/
- https://www.anatod.com/ispkeeper/tv/
- https://www.anatod.com/ispkeeper/api/
- https://www.anatod.com/blog/2026/04/10/nuevo-modulo-de-suscripciones-en-ispkeeper-amplia-tus-ingresos-mas-alla-del-internet/
- https://www.anatod.com/events/
- https://wiki.anatod.com/api/
