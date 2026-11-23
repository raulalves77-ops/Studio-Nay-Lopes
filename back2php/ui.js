/* Interface apenas: tema claro/escuro, menu mobile e galeria.
   Dados, regras e segurança ficam todos no PHP. */
(function () {
    'use strict';

    /* ---------- Tema ---------- */
    var THEME_KEY = 'sbd-theme';

    function aplicarTema(tema) {
        document.documentElement.setAttribute('data-theme', tema);
        try { localStorage.setItem(THEME_KEY, tema); } catch (e) {}
        var btn = document.getElementById('theme-toggle');
        if (!btn) return;
        var icone = btn.querySelector('i');
        if (icone) icone.className = tema === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
        btn.setAttribute('aria-label', tema === 'dark' ? 'Ativar modo claro' : 'Ativar modo escuro');
        btn.title = tema === 'dark' ? 'Modo claro' : 'Modo escuro';
    }

    function temaPreferido() {
        var salvo = null;
        try { salvo = localStorage.getItem(THEME_KEY); } catch (e) {}
        if (salvo === 'dark' || salvo === 'light') return salvo;
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    function iniciarTema() {
        aplicarTema(temaPreferido());
        var btn = document.getElementById('theme-toggle');
        if (btn) {
            btn.addEventListener('click', function () {
                var atual = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
                aplicarTema(atual === 'dark' ? 'light' : 'dark');
            });
        }
    }

    /* ---------- Menu mobile ---------- */
    function iniciarMenu() {
        var toggle = document.querySelector('.nav-toggle');
        var links = document.querySelector('.nav-links');
        if (!toggle || !links) return;

        function icone(aberto) {
            var i = toggle.querySelector('i');
            if (i) i.className = aberto ? 'fa-solid fa-xmark' : 'fa-solid fa-bars';
            toggle.setAttribute('aria-expanded', String(aberto));
            toggle.setAttribute('aria-label', aberto ? 'Fechar menu' : 'Abrir menu');
        }
        function fechar() { links.classList.remove('open'); icone(false); }

        toggle.addEventListener('click', function () { icone(links.classList.toggle('open')); });
        links.querySelectorAll('a').forEach(function (a) { a.addEventListener('click', fechar); });
        window.addEventListener('resize', function () { if (window.innerWidth > 820) fechar(); });
        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape' && links.classList.contains('open')) { fechar(); toggle.focus(); }
        });
    }

    /* ---------- Galeria (lightbox) ---------- */
    function iniciarGaleria() {
        var box = document.getElementById('media-lightbox');
        var conteudo = document.getElementById('media-lightbox-content');
        var legenda = document.getElementById('media-lightbox-caption');
        if (!box || !conteudo || !legenda) return;

        function fechar() {
            conteudo.querySelectorAll('video').forEach(function (v) { v.pause(); v.removeAttribute('src'); v.load(); });
            conteudo.replaceChildren();
            legenda.textContent = '';
            box.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }

        document.querySelectorAll('.gallery-item').forEach(function (item) {
            item.addEventListener('click', function () {
                conteudo.replaceChildren();
                var img = item.querySelector('img');
                var videoSrc = item.dataset.videoSrc;
                if (videoSrc) {
                    var v = document.createElement('video');
                    v.src = videoSrc; v.controls = true; v.playsInline = true; v.autoplay = true;
                    conteudo.appendChild(v);
                    var t = v.play(); if (t && t.catch) t.catch(function () {});
                } else if (img) {
                    var g = document.createElement('img');
                    g.src = img.currentSrc || img.src; g.alt = img.alt || 'Imagem ampliada';
                    conteudo.appendChild(g);
                } else { return; }
                legenda.textContent = item.dataset.caption || (img ? img.alt : '');
                box.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            });
        });

        var x = box.querySelector('.media-lightbox-close');
        if (x) x.addEventListener('click', fechar);
        box.addEventListener('click', function (ev) { if (ev.target === box) fechar(); });
        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape' && box.getAttribute('aria-hidden') === 'false') fechar();
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        iniciarTema();
        iniciarMenu();
        iniciarGaleria();
    });
})();
