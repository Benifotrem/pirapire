<?php

return [

    'meta_title' => 'Ayuda y Preguntas Frecuentes — Pirapire.pro',
    'meta_description' => 'Manual de uso paso a paso y preguntas frecuentes sobre Pirapire.pro: login soberano, alertas P2P, contratar y trabajar con Escrow Lightning, y más.',

    'hero_badge' => 'Centro de ayuda',
    'hero_title' => 'Manual de uso y preguntas frecuentes',
    'hero_subtitle' => 'Todo lo que necesitás para usar Pirapire.pro, paso a paso. Elegí una guía abajo o buscá tu pregunta directamente.',
    'search_placeholder' => 'Buscar en las preguntas frecuentes… (ej: "comisión", "disputa", "Telegram")',
    'search_no_results' => 'No encontramos ninguna pregunta que coincida con tu búsqueda.',

    'nav_manual' => 'Manual de uso',
    'nav_faq' => 'Preguntas frecuentes',

    'manual_title' => 'Manual paso a paso',
    'manual_subtitle' => 'Elegí qué querés hacer — cada guía es independiente, no hace falta leerlas en orden.',

    'tabs' => [
        'login' => '⚡ Iniciar sesión',
        'alerts' => '🔔 Alertas P2P',
        'telegram' => '📨 Vincular Telegram',
        'hire' => '💼 Contratar (cliente)',
        'freelance' => '🛠️ Trabajar y cobrar',
        'commands' => '🤖 Comandos del bot',
    ],

    'login' => [
        'title' => 'Iniciar sesión con tu billetera Lightning',
        'intro' => 'Pirapire.pro no tiene registro con email ni contraseña. Tu identidad es la clave pública de tu billetera Lightning — el estándar se llama <code>LNURL-auth</code>.',
        'steps' => [
            ['title' => 'Andá a la página de login', 'body' => 'Entrá a <a href="/login" class="text-blue-600 underline">pirapire.pro/login</a>. Vas a ver un código QR generado en el momento.'],
            ['title' => 'Abrí tu billetera Lightning', 'body' => 'Cualquier billetera compatible con LNURL-auth sirve: Phoenix, Blink, Zeus, Alby, entre otras. Si estás en el mismo celular, tocá el botón "Abrir en la billetera" en vez de escanear.'],
            ['title' => 'Escaneá el código QR', 'body' => 'Tu billetera va a mostrarte una pantalla pidiendo confirmar el inicio de sesión — no se te va a pedir ninguna firma de pago, ni se mueve ningún sat en este paso.'],
            ['title' => 'Confirmá en la billetera', 'body' => 'Al aceptar, tu billetera firma un desafío con tu clave de linking y se lo manda al servidor. La página de pirapire.pro detecta la confirmación sola (hace polling cada 2 segundos) y te redirige a tu panel — no hace falta que hagas nada más.'],
        ],
        'tip' => 'Como no hay email ni contraseña, tampoco existe un "recuperar cuenta" — tu clave de billetera es tu identidad. Si perdés el acceso a esa billetera, perdés el acceso a esa cuenta. Guardá bien tu frase semilla.',
    ],

    'alerts' => [
        'title' => 'Configurar alertas P2P',
        'intro' => 'Las alertas te avisan por Telegram cada vez que aparece una orden de compra/venta P2P (en RoboSats o Mostro) que coincide con lo que buscás.',
        'steps' => [
            ['title' => 'Iniciá sesión', 'body' => 'Entrá con tu billetera (ver la guía "Iniciar sesión") y andá a tu panel (<a href="/dashboard" class="text-blue-600 underline">/dashboard</a>).'],
            ['title' => 'Completá el formulario "Nueva alerta P2P"', 'body' => 'Elegí la moneda (PYG o USD), la fuente (RoboSats, Mostro, o ambas), el tipo de orden (compra, venta, o cualquiera) y, si querés, un rango de monto mínimo/máximo.'],
            ['title' => 'El monto mínimo/máximo es en la moneda elegida, no en sats', 'body' => 'Si elegiste PYG, esos números son guaraníes; si elegiste USD, son dólares. Es el monto de la orden P2P (lo que vas a pagar o cobrar en fiat), no una cantidad de satoshis.'],
            ['title' => 'Creá la alerta', 'body' => 'A partir de ahí, cada vez que aparezca una orden nueva que coincida, te llega un mensaje de Telegram con los detalles y un enlace o comando para tomarla.'],
            ['title' => 'Pausá, activá o borrá cuando quieras', 'body' => 'Desde "Mis alertas" en el mismo panel podés pausar una alerta temporalmente, reactivarla, o eliminarla del todo.'],
        ],
        'tip' => 'Mientras estamos en fase de validación con la comunidad, todas las alertas (plan gratuito y VIP) llegan al instante, sin diferencia de velocidad. Para recibir cualquier alerta necesitás tener Telegram vinculado a tu cuenta — ver la guía "Vincular Telegram".',
    ],

    'telegram' => [
        'title' => 'Vincular tu cuenta con Telegram',
        'intro' => 'Si iniciaste sesión con tu billetera, tu cuenta todavía no tiene un chat de Telegram asociado — sin vincularlo, no vas a recibir alertas ni notificaciones. Mandarle <code>/start</code> al bot por las suyas no alcanza: eso crea una cuenta nueva vacía en vez de completar la tuya.',
        'steps' => [
            ['title' => 'Andá a tu panel', 'body' => 'En <a href="/dashboard" class="text-blue-600 underline">/dashboard</a>, si todavía no vinculaste Telegram vas a ver un aviso con el link "Vincular ahora →".'],
            ['title' => 'Se genera un código de un solo uso', 'body' => 'La página te muestra un código de 6 caracteres, válido por 10 minutos (por ejemplo <code>/vincular AB12CD</code>).'],
            ['title' => 'Mandale ese mensaje exacto al bot BØLT', 'body' => 'Abrí el chat de Telegram con BØLT y escribí el comando completo, tal cual aparece en la página.'],
            ['title' => 'Listo, la página se actualiza sola', 'body' => 'En cuanto el bot recibe el código, la página (que está esperando la confirmación) te redirige automáticamente a tu panel, ya con Telegram vinculado.'],
        ],
        'tip' => 'Si ya le habías mandado /start al bot antes de vincular con este método, no hay problema — el sistema fusiona esa conversación vacía con tu cuenta real automáticamente, siempre que esa conversación no tenga ya alertas o trabajos propios (en ese caso te va a pedir que lo resolvamos a mano, para no perder nada).',
    ],

    'hire' => [
        'title' => 'Contratar un trabajo (como cliente)',
        'intro' => 'Todo el proceso de contratar, pagar y resolver problemas ocurre adentro de Pirapire — no hace falta ponerse de acuerdo con nadie por fuera de la plataforma.',
        'steps' => [
            ['title' => 'Publicá el trabajo', 'body' => 'Desde el <a href="/dashboard/escrow" class="text-blue-600 underline">tablón de trabajos</a> (o con el comando <code>/escrow create &lt;monto_sats&gt; &lt;descripción&gt;</code> en Telegram), indicá cuántos sats vas a pagar y describí la tarea. Todavía no se te cobra nada en este paso — no hay factura hasta que elijas a alguien.'],
            ['title' => 'Esperá postulaciones', 'body' => 'Los freelancers ven tu trabajo en el tablón y se postulan con un mensaje explicando por qué son la persona indicada. Vas a verlas en la sección "Trabajos que publiqué".'],
            ['title' => 'Elegí un freelancer', 'body' => 'Revisá las postulaciones y aceptá una. En ese momento se genera la factura de fondeo — por el monto que ofreciste (más comisión, actualmente 0%).'],
            ['title' => 'Pagá la factura de fondeo', 'body' => 'Pagala desde tu propia billetera Lightning. Los fondos quedan resguardados en el wallet de la plataforma hasta que el trabajo se complete.'],
            ['title' => 'Esperá la entrega', 'body' => 'Cuando el freelancer termina, marca el trabajo como entregado y sube su factura de cobro — a veces también una captura de pantalla u otra prueba, si el trabajo lo requiere.'],
            ['title' => 'Revisá y liberá el pago', 'body' => 'Si el trabajo está bien, liberá el pago con un clic — se le paga automáticamente al freelancer. Si hay un problema, abrí una disputa en vez de liberar.'],
        ],
        'tip' => 'Podés hacer todo este proceso desde el dashboard web, la Mini App de Telegram, o los comandos del bot — es el mismo tablón, publicar o postularte desde cualquiera de los tres aparece igual en los otros dos.',
    ],

    'freelance' => [
        'title' => 'Trabajar y cobrar (como freelancer)',
        'intro' => 'Buscá trabajos abiertos, postulate, y cobrá directo a tu billetera Lightning cuando el cliente libere el pago.',
        'steps' => [
            ['title' => 'Buscá trabajos abiertos', 'body' => 'En el <a href="/dashboard/escrow" class="text-blue-600 underline">tablón</a> (o con <code>/escrow browse</code> en Telegram) vas a ver los trabajos publicados por otros clientes. También aparecen rotando en el cartel LED del sitio, sin necesidad de tener sesión iniciada.'],
            ['title' => 'Postulate', 'body' => 'Contale al cliente en un mensaje corto por qué sos la persona indicada para el trabajo.'],
            ['title' => 'Esperá a que te elijan', 'body' => 'Si el cliente te acepta, el trabajo pasa a estado "fondeado" en cuanto paga la factura — recién ahí empezá el trabajo.'],
            ['title' => 'Hacé el trabajo y marcalo como entregado', 'body' => 'Cuando termines, subí tu propia factura Lightning (bolt11) para cobrar. Si el trabajo lo requiere (por ejemplo, "compartí este posteo y subí una captura"), podés adjuntar una imagen como prueba.'],
            ['title' => 'Cobrá', 'body' => 'Cuando el cliente confirme y libere el pago, tu factura se paga automáticamente — no hace falta que se la reclames ni se la vuelvas a pasar.'],
        ],
        'tip' => 'Si el cliente no libera el pago sin motivo, o hay cualquier desacuerdo, vos también podés abrir una disputa para que la revise un administrador — no depende únicamente del cliente.',
    ],

    'commands' => [
        'title' => 'Comandos del bot BØLT en Telegram',
        'intro' => 'Todo lo que se puede hacer desde el dashboard web también se puede hacer por comando de texto, hablando directo con el bot.',
        'groups' => [
            [
                'label' => 'General',
                'items' => [
                    ['cmd' => '/start', 'desc' => 'Da de alta tu chat y muestra la bienvenida.'],
                    ['cmd' => '/mempool', 'desc' => 'Altura de bloque actual y tarifas recomendadas.'],
                    ['cmd' => '/vip', 'desc' => 'Estado de tu suscripción VIP.'],
                    ['cmd' => '/help', 'desc' => 'Lista de comandos disponibles.'],
                ],
            ],
            [
                'label' => 'Escrow (contratar y trabajar)',
                'items' => [
                    ['cmd' => '/escrow create &lt;monto_sats&gt; &lt;descripción&gt;', 'desc' => 'Publica un trabajo abierto.'],
                    ['cmd' => '/escrow browse', 'desc' => 'Lista trabajos abiertos de otros clientes.'],
                    ['cmd' => '/escrow apply &lt;id&gt; &lt;mensaje&gt;', 'desc' => 'Te postulás a un trabajo abierto.'],
                    ['cmd' => '/escrow applications &lt;id&gt;', 'desc' => 'Ves las postulaciones a tu trabajo.'],
                    ['cmd' => '/escrow accept &lt;id_trabajo&gt; &lt;id_postulación&gt;', 'desc' => 'Elegís un freelancer y se genera la factura de fondeo.'],
                    ['cmd' => '/escrow deliver &lt;id&gt; &lt;bolt11&gt;', 'desc' => 'Marcás el trabajo entregado y subís tu factura de cobro.'],
                    ['cmd' => '/escrow release &lt;id&gt;', 'desc' => 'Liberás el pago al freelancer.'],
                    ['cmd' => '/escrow dispute &lt;id&gt; [motivo]', 'desc' => 'Abrís una disputa para que la revise un admin.'],
                    ['cmd' => '/escrow status &lt;id&gt;', 'desc' => 'Consultás el estado de un trabajo.'],
                    ['cmd' => '/escrow cancel &lt;id&gt;', 'desc' => 'Cancelás un trabajo propio sin freelancer asignado todavía.'],
                ],
            ],
        ],
        'tip' => 'La subida de una prueba de entrega (captura de pantalla) solo está disponible desde el dashboard web o la Mini App — los comandos de texto no permiten adjuntar imágenes.',
    ],

    'faq_title' => 'Preguntas frecuentes',
    'faq_subtitle' => 'Si tu pregunta no está acá, revisá el manual de arriba o escribinos.',

    'faq_categories' => [
        'account' => [
            'label' => 'Cuenta y acceso',
            'items' => [
                ['q' => '¿Necesito email o contraseña para usar Pirapire?', 'a' => 'No. Tu identidad es la clave pública de tu billetera Lightning (LNURL-auth). No hay base de datos de contraseñas que filtrar.'],
                ['q' => '¿Qué billeteras Lightning puedo usar?', 'a' => 'Cualquiera compatible con el estándar LNURL-auth: Phoenix, Blink, Zeus, Alby, entre otras.'],
                ['q' => 'Perdí el acceso a mi billetera, ¿puedo recuperar mi cuenta?', 'a' => 'No hay un mecanismo de "recuperar cuenta" por email, a propósito — tu clave de billetera es tu identidad. Guardá bien tu frase semilla; es la misma responsabilidad que tenés con cualquier billetera Bitcoin.'],
                ['q' => '¿Por qué mandé /start al bot y mi Telegram no quedó vinculado?', 'a' => 'Mandar /start a secas solo funciona si tu cuenta no existía todavía. Si ya te registraste con tu billetera, tenés que usar el botón "Vincular ahora" de tu panel, que te da un código para mandarle al bot (/vincular CODIGO) — así se conecta con la cuenta correcta en vez de crear una nueva.'],
            ],
        ],
        'alerts' => [
            'label' => 'Alertas P2P',
            'items' => [
                ['q' => '¿Qué diferencia hay entre el plan gratuito y VIP?', 'a' => 'Por ahora, mientras dure la fase de validación con la comunidad, ninguna en velocidad: todas las alertas (gratis y VIP) llegan al instante. Cuando el plan VIP pase a ser pago, el plan gratuito va a tener una demora de unos minutos frente al instantáneo de VIP.'],
                ['q' => 'Puse un monto mínimo/máximo, ¿en qué moneda es?', 'a' => 'Es en la moneda que elegiste para esa alerta (PYG o USD) — es el monto de la orden P2P en fiat, no una cantidad de satoshis.'],
                ['q' => '¿De dónde salen las ofertas P2P?', 'a' => 'De RoboSats y de Mostro. Podés elegir una fuente específica o "todas" al crear tu alerta.'],
                ['q' => '¿Cómo consigo el plan VIP?', 'a' => 'Por ahora se asigna manualmente — escribinos por Telegram o contactá a un admin.'],
            ],
        ],
        'escrow' => [
            'label' => 'Contratar y trabajar (Escrow)',
            'items' => [
                ['q' => '¿Quién guarda el dinero mientras se hace el trabajo?', 'a' => 'Los fondos quedan en el wallet Lightning de la plataforma (un escrow custodial) desde que el cliente paga la factura de fondeo hasta que se libera el pago o se resuelve una disputa.'],
                ['q' => '¿Cuánto cobra Pirapire de comisión?', 'a' => 'Actualmente 0% — la plataforma está en una fase de validación con la comunidad ("Modo Comunitario"). Ese porcentaje puede cambiar en el futuro; siempre se muestra el valor vigente antes de publicar un trabajo.'],
                ['q' => '¿Qué pasa si el freelancer no entrega el trabajo?', 'a' => 'El cliente (o el freelancer, si el problema es al revés) puede abrir una disputa. Un administrador la revisa y decide si el pago se libera al freelancer o se reembolsa al cliente.'],
                ['q' => '¿Qué pasa si el cliente no libera el pago sin motivo?', 'a' => 'El freelancer también puede abrir una disputa una vez que entregó el trabajo — no depende únicamente de que el cliente actúe.'],
                ['q' => '¿Puedo cancelar un trabajo que publiqué?', 'a' => 'Sí, mientras siga "abierto" (sin freelancer asignado todavía). Una vez que aceptaste a alguien y se generó la factura de fondeo, ya no se puede cancelar por tu cuenta — en ese caso, si nunca se paga, se cancela solo al vencer.'],
                ['q' => '¿Tengo que usar Telegram para todo esto?', 'a' => 'No. El mismo tablón de trabajos está disponible desde el dashboard web (sin Telegram), la Mini App de Telegram, y los comandos de texto del bot — publicar, postularte o gestionar un trabajo desde cualquiera de los tres se refleja igual en los otros dos.'],
                ['q' => '¿Qué es la "prueba de entrega"?', 'a' => 'Para trabajos donde lo que hay que entregar es una acción verificable (por ejemplo "compartí este posteo"), el freelancer puede adjuntar una captura de pantalla u otra imagen al marcar el trabajo como entregado, para que el cliente la revise antes de liberar el pago. Es opcional y solo está disponible desde el dashboard web o la Mini App.'],
                ['q' => '¿Dónde veo los trabajos disponibles sin tener cuenta?', 'a' => 'Los trabajos abiertos rotan en el cartel LED del header del sitio, visibles para cualquier visitante. Al hacer clic te lleva al tablón — si no iniciaste sesión, te invita a hacerlo primero.'],
            ],
        ],
        'payments' => [
            'label' => 'Pagos y billetera',
            'items' => [
                ['q' => '¿Es un hold invoice de verdad, con los fondos "retenidos" en un HTLC?', 'a' => 'No. LNbits (el software que usa la plataforma) no tiene esa funcionalidad. Es un escrow custodial clásico: cuando pagás la factura de fondeo, el pago se liquida de inmediato en el wallet de la plataforma, que lo retiene hasta liberar o reembolsar.'],
                ['q' => '¿Qué pasa si no pago la factura de fondeo a tiempo?', 'a' => 'La asignación se cancela automáticamente cuando vence (normalmente en una hora) y el trabajo vuelve a estar disponible para que el cliente elija otro freelancer.'],
            ],
        ],
        'security' => [
            'label' => 'Seguridad y transparencia',
            'items' => [
                ['q' => '¿El código de Pirapire es auditable?', 'a' => 'Sí, el repositorio es público justamente para que cualquiera pueda revisarlo antes de confiarle sus sats a la plataforma.'],
                ['q' => '¿Cómo reporto un problema de seguridad?', 'a' => 'Seguí las instrucciones del archivo SECURITY.md del repositorio para un reporte responsable.'],
            ],
        ],
    ],

];
