/* SONIDOS DE SMARTTRIVIA
   - Música de fondo y efectos: archivos mp3
   - Ruleta y tic-tac del reloj: se generan por código
 */
var Sonidos = (function () {

    // ====== ARCHIVOS MP3 ======
    var ARCHIVOS = {
        correcta:  '/public/audio/correcta.mp3',  // acertó una pregunta
        categoria: '/public/audio/categoria.mp3', // terminó una categoría y pasa a otra
        error:     '/public/audio/error.mp3',     // terminó la partida (respuesta incorrecta o sin tiempo)
        fin:       '/public/audio/fin.mp3'        // respondió todas las preguntas
    };

    var MUSICA = '/public/audio/musica.mp3';
    var VOLUMEN_MUSICA = 0.25, VOLUMEN_EFECTOS = 0.7;
    // ==============================================================================

    var silenciado = false;
    try { silenciado = localStorage.getItem('st_silencio') === '1'; } catch (e) {}

    /* ---------- Efectos generados por código (ruleta y reloj) ---------- */
    var ctx = null, salida = null;
    function audio() {
        if (!ctx) {
            var Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) { return null; }
            ctx = new Ctx();
            salida = ctx.createGain(); salida.connect(ctx.destination);
        }
        return ctx;
    }
    function nota(frecuencia, inicio, duracion, tipo, volumen) {
        var o = ctx.createOscillator(), g = ctx.createGain();
        o.type = tipo || 'sine';
        o.frequency.value = frecuencia;
        g.gain.setValueAtTime(0.0001, inicio);
        g.gain.exponentialRampToValueAtTime(volumen || 0.2, inicio + 0.01);
        g.gain.exponentialRampToValueAtTime(0.0001, inicio + duracion);
        o.connect(g); g.connect(salida);
        o.start(inicio); o.stop(inicio + duracion + 0.05);
    }
    var efectosCodigo = {
        tictac: function (t) { nota(1000, t, 0.05, 'square', 0.08); },
        listo:  function (t) { [523, 659, 784].forEach(function (f, i) { nota(f, t + i * 0.1, 0.25); }); }
    };

    // Clicks de la ruleta: al principio muy seguidos y al final cada vez más espaciados
    function ruleta(segundos) {
        if (silenciado || !audio() || ctx.state !== 'running') { return; }
        var total = 40, t0 = ctx.currentTime;
        for (var k = 8; k <= total; k++) {
            nota(800, t0 + segundos * Math.pow(k / total, 2), 0.03, 'triangle', 0.15);
        }
    }

    function efecto(nombre) {
        if (silenciado) { return; }
        if (ARCHIVOS[nombre]) { // efecto con mp3
            var a = new Audio(ARCHIVOS[nombre]);
            a.volume = VOLUMEN_EFECTOS;
            a.play().catch(function () {}); // si el navegador lo bloquea, no pasa nada
            return;
        }
        if (!efectosCodigo[nombre] || !audio() || ctx.state !== 'running') { return; }
        efectosCodigo[nombre](ctx.currentTime);
    }

    /* ---------- Música de fondo (mp3 en loop) ---------- */
    var musica = new Audio(MUSICA);
    musica.loop = true;
    musica.volume = VOLUMEN_MUSICA;

    // Como cada pantalla recarga la página, se guarda en qué segundo iba para que no arranque de cero
    var posicion = 0;
    try { posicion = parseFloat(sessionStorage.getItem('st_pos')) || 0; } catch (e) {}
    musica.addEventListener('loadedmetadata', function () {
        if (posicion < musica.duration) { musica.currentTime = posicion; }
    });
    window.addEventListener('pagehide', function () {
        try { sessionStorage.setItem('st_pos', musica.currentTime); } catch (e) {}
    });

    // Hace sonar la música (si no está silenciada y no está sonando ya)
    function iniciarMusica() {
        if (silenciado || !musica.paused) { return; }
        var p = musica.play();
        if (p) { p.catch(function () {}); }
    }

    // Respaldo: algunos navegadores no dejan sonar hasta el primer click.
    // Si pasa eso, con el primer click se vuelve a intentar.
    function activar() {
        if (silenciado) { return; }
        iniciarMusica();
        if (audio() && ctx.state === 'suspended') { ctx.resume(); }
    }
    ['click', 'keydown', 'touchstart'].forEach(function (e) { document.addEventListener(e, activar); });

    // La música suena apenas se carga la página
    iniciarMusica();

    /* ---------- Botón para silenciar / activar todo ---------- */
    function crearBoton() {
        var estilo = document.createElement('style');
        estilo.textContent = '#btn-sonido{position:fixed;right:16px;bottom:16px;z-index:999;width:48px;height:48px;border-radius:50%;' +
            'border:2px solid #fff;background:#0b2d63;color:#fff;font-size:1.4rem;cursor:pointer;box-shadow:0 4px 12px rgb(0 0 0/.3)}' +
            '#btn-sonido:hover{background:#1e5aa8}#btn-sonido:focus-visible{outline:3px solid #f59e0b;outline-offset:2px}';
        document.head.appendChild(estilo);

        var btn = document.createElement('button');
        btn.type = 'button'; btn.id = 'btn-sonido';
        btn.setAttribute('aria-label', 'Silenciar sonido');
        function pintar() {
            btn.textContent = silenciado ? '🔇' : '🔊';
            btn.setAttribute('aria-pressed', silenciado ? 'true' : 'false');
            btn.title = silenciado ? 'Activar sonido' : 'Silenciar sonido';
        }
        btn.addEventListener('click', function () {
            silenciado = !silenciado;
            try { localStorage.setItem('st_silencio', silenciado ? '1' : '0'); } catch (e) {}
            if (silenciado) { musica.pause(); } else { activar(); }
            pintar();
        });
        pintar();
        document.body.appendChild(btn);
    }
    if (document.body) { crearBoton(); } else { document.addEventListener('DOMContentLoaded', crearBoton); }

    return { efecto: efecto, ruleta: ruleta };
})();