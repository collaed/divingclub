<?php
/**
 * CEP Landing Page — Standalone, multi-language, with Joomla login
 * PHP 5.6 compatible
 */

// Language detection
$supported = ['fr', 'en', 'de', 'pt', 'it', 'es', 'nl', 'pl', 'ro'];
$lang = 'fr';
if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
    foreach (explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']) as $part) {
        $code = strtolower(substr(trim(explode(';', $part)[0]), 0, 2));
        if (in_array($code, $supported)) {
            $lang = $code;
            break;
        }
    }
}
if (isset($_GET['lang']) && in_array($_GET['lang'], $supported)) {
    $lang = $_GET['lang'];
}

$t = [
    'tagline' => [
        'fr' => 'Plongez avec nous au Luxembourg',
        'en' => 'Dive with us in Luxembourg',
        'de' => 'Tauchen Sie mit uns in Luxemburg',
        'pt' => 'Mergulhe connosco no Luxemburgo',
        'it' => 'Immergiti con noi in Lussemburgo',
        'es' => 'Bucea con nosotros en Luxemburgo',
        'nl' => 'Duik met ons in Luxemburg',
        'pl' => 'Nurkuj z nami w Luksemburgu',
        'ro' => 'Scufunda-te cu noi in Luxemburg',
    ],
    'login' => ['fr' => 'Connexion', 'en' => 'Login', 'de' => 'Anmelden', 'pt' => 'Entrar', 'it' => 'Accedi', 'es' => 'Iniciar sesion', 'nl' => 'Inloggen', 'pl' => 'Zaloguj sie', 'ro' => 'Autentificare'],
    'username' => ['fr' => "Nom d'utilisateur", 'en' => 'Username', 'de' => 'Benutzername', 'pt' => 'Utilizador', 'it' => 'Nome utente', 'es' => 'Usuario', 'nl' => 'Gebruikersnaam', 'pl' => 'Nazwa uzytkownika', 'ro' => 'Utilizator'],
    'password' => ['fr' => 'Mot de passe', 'en' => 'Password', 'de' => 'Passwort', 'pt' => 'Palavra-passe', 'it' => 'Password', 'es' => 'Contrasena', 'nl' => 'Wachtwoord', 'pl' => 'Haslo', 'ro' => 'Parola'],
    'remember' => ['fr' => 'Se souvenir de moi', 'en' => 'Remember me', 'de' => 'Angemeldet bleiben', 'pt' => 'Lembrar-me', 'it' => 'Ricordami', 'es' => 'Recordarme', 'nl' => 'Onthoud mij', 'pl' => 'Zapamietaj mnie', 'ro' => 'Tine-ma minte'],
    'forgot' => ['fr' => 'Mot de passe oublie ?', 'en' => 'Forgot password?', 'de' => 'Passwort vergessen?', 'pt' => 'Esqueceu a palavra-passe?', 'it' => 'Password dimenticata?', 'es' => 'Contrasena olvidada?', 'nl' => 'Wachtwoord vergeten?', 'pl' => 'Zapomnialaes hasla?', 'ro' => 'Ai uitat parola?'],
    'try_diving' => ['fr' => 'Essayer la plongee', 'en' => 'Try Diving', 'de' => 'Tauchen probieren', 'pt' => 'Experimentar mergulho', 'it' => "Prova l'immersione", 'es' => 'Prueba el buceo', 'nl' => 'Probeer duiken', 'pl' => 'Sprobuj nurkowania', 'ro' => 'Incearca scufundarea'],
    'federations' => ['fr' => 'f&eacute;d&eacute;rations', 'en' => 'federations', 'de' => 'Verb&auml;nde', 'pt' => 'federa&ccedil;&otilde;es', 'it' => 'federazioni', 'es' => 'federaciones', 'nl' => 'federaties', 'pl' => 'federacji', 'ro' => 'federatii'],
    'members' => ['fr' => 'Membres', 'en' => 'Members', 'de' => 'Mitglieder', 'pt' => 'Membros', 'it' => 'Membri', 'es' => 'Miembros', 'nl' => 'Leden', 'pl' => 'Czlonkowie', 'ro' => 'Membri'],
    'nationalities' => ['fr' => 'Nationalites', 'en' => 'Nationalities', 'de' => 'Nationalitaten', 'pt' => 'Nacionalidades', 'it' => 'Nazionalita', 'es' => 'Nacionalidades', 'nl' => 'Nationaliteiten', 'pl' => 'Narodowosci', 'ro' => 'Nationalitati'],
    'founded' => ['fr' => 'Fond&eacute; en', 'en' => 'Founded in', 'de' => 'Gegr&uuml;ndet', 'pt' => 'Fundado em', 'it' => 'Fondato nel', 'es' => 'Fundado en', 'nl' => 'Opgericht in', 'pl' => 'Zalozony w', 'ro' => 'Fondat in'],
    'women' => ['fr' => 'Femmes', 'en' => 'Women', 'de' => 'Frauen', 'pt' => 'Mulheres', 'it' => 'Donne', 'es' => 'Mujeres', 'nl' => 'Vrouwen', 'pl' => 'Kobiety', 'ro' => 'Femei'],
    'val1' => ['fr' => 'Du premier souffle sous l\'eau jusqu\'a moniteur - tous niveaux bienvenus.', 'en' => 'From first breath underwater to instructor - all levels welcome.', 'de' => 'Vom ersten Atemzug unter Wasser bis zum Tauchlehrer - alle Stufen willkommen.', 'pt' => 'Da primeira respiracao debaixo de agua ate instrutor - todos os niveis sao bem-vindos.', 'it' => 'Dal primo respiro sott\'acqua all\'istruttore - tutti i livelli sono benvenuti.', 'es' => 'Desde la primera respiracion bajo el agua hasta instructor - todos los niveles.', 'nl' => 'Van eerste ademhaling onder water tot instructeur - alle niveaus welkom.', 'pl' => 'Od pierwszego oddechu pod woda do instruktora - wszystkie poziomy.', 'ro' => 'De la prima respiratie sub apa la instructor - toate nivelurile sunt binevenite.'],
    'val2' => ['fr' => 'Un club veritablement international - 16 nationalites, unis par la mer.', 'en' => 'A truly international club - 16 nationalities, united by the sea.', 'de' => 'Ein wirklich internationaler Verein - 16 Nationalitaten, vereint durch das Meer.', 'pt' => 'Um clube verdadeiramente internacional - 16 nacionalidades, unidos pelo mar.', 'it' => 'Un club veramente internazionale - 16 nazionalita, uniti dal mare.', 'es' => 'Un club verdaderamente internacional - 16 nacionalidades, unidos por el mar.', 'nl' => 'Een echt internationale club - 16 nationaliteiten, verenigd door de zee.', 'pl' => 'Prawdziwie miedzynarodowy klub - 16 narodowosci, zjednoczonych przez morze.', 'ro' => 'Un club cu adevarat international - 16 nationalitati, uniti de mare.'],
    'val3' => ['fr' => 'Entrainements piscine hebdomadaires, plongees en eau libre et voyages - toute l\'annee.', 'en' => 'Weekly pool sessions, open water dives, and trips abroad - all year round.', 'de' => 'Wochentliches Pool-Training, Freiwassertauchen und Reisen - das ganze Jahr.', 'pt' => 'Treinos semanais na piscina, mergulhos em aguas abertas e viagens - todo o ano.', 'it' => 'Sessioni settimanali in piscina, immersioni in acque libere e viaggi - tutto l\'anno.', 'es' => 'Sesiones semanales en piscina, buceo en aguas abiertas y viajes - todo el ano.', 'nl' => 'Wekelijkse zwembadtraining, buitenwaterduiken en reizen - het hele jaar door.', 'pl' => 'Cotygodniowe treningi na basenie, nurkowanie i wyjazdy - caly rok.', 'ro' => 'Sesiuni saptamanale la piscina, scufundari in apa deschisa si excursii - tot anul.'],
    'ready' => ['fr' => 'Pret a plonger ?', 'en' => 'Ready to dive?', 'de' => 'Bereit zu tauchen?', 'pt' => 'Pronto para mergulhar?', 'it' => 'Pronto a immergerti?', 'es' => 'Listo para bucear?', 'nl' => 'Klaar om te duiken?', 'pl' => 'Gotowy do nurkowania?', 'ro' => 'Gata de scufundare?'],
    'contact_us' => ['fr' => 'Contactez-nous', 'en' => 'Contact us', 'de' => 'Kontaktieren Sie uns', 'pt' => 'Contacte-nos', 'it' => 'Contattaci', 'es' => 'Contactenos', 'nl' => 'Contacteer ons', 'pl' => 'Skontaktuj sie z nami', 'ro' => 'Contactati-ne'],
];

function tr($key)
{
    global $t, $lang;
    if (isset($t[$key][$lang])) {
        return $t[$key][$lang];
    }
    if (isset($t[$key]['en'])) {
        return $t[$key]['en'];
    }

    return $key;
}

$clubName = 'Club Europ&eacute;en de Plong&eacute;e';
$clubNameRaw = 'Club Européen de Plongée';
$clubEmail = 'info@clubcep.eu';
$years = date('Y') - 1972;
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $clubNameRaw; ?></title>
    <link rel="icon" href="images/club-logo.png" type="image/png">
    <style>
    :root{--pr:#003366;--ac:#00e5ff}*{margin:0;padding:0;box-sizing:border-box}body{font-family:system-ui,-apple-system,sans-serif;overflow-x:hidden;color:#222}
    .hero{position:relative;min-height:100vh;display:flex;align-items:center;justify-content:center;overflow:hidden}
    .hero-bg{position:absolute;top:0;left:0;right:0;bottom:0}.hero-bg img{position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;opacity:0;transition:opacity 1.5s}.hero-bg img.active{opacity:1}
    .hero:after{content:'';position:absolute;top:0;left:0;right:0;bottom:0;background:linear-gradient(180deg,rgba(0,20,50,.6),rgba(0,20,50,.4))}
    .hero-c{position:relative;z-index:2;text-align:center;color:#fff;padding:2rem}
    .hero-c h1{font-size:clamp(2.5rem,7vw,5rem);font-weight:800;letter-spacing:-1px;text-shadow:0 2px 20px rgba(0,0,0,.5)}
    .hero-c p{font-size:clamp(1rem,2.5vw,1.4rem);opacity:.85;margin:1rem auto 2.5rem;max-width:500px}
    .btn{display:inline-block;padding:.9rem 2.5rem;border-radius:50px;font-weight:700;font-size:1rem;text-decoration:none;transition:transform .2s,box-shadow .2s;cursor:pointer;border:none}
    .btn:hover{transform:translateY(-2px);box-shadow:0 8px 25px rgba(0,0,0,.3)}
    .btn-a{background:var(--ac);color:#000}.btn-o{border:2px solid rgba(255,255,255,.7);color:#fff;background:transparent}.btn-o:hover{background:rgba(255,255,255,.15);color:#fff}
    .scroll-d{position:absolute;bottom:2rem;left:50%;transform:translateX(-50%);z-index:2;color:rgba(255,255,255,.5);font-size:2rem;animation:bounce 2s infinite}
    @keyframes bounce{0%,100%{transform:translateX(-50%) translateY(0)}50%{transform:translateX(-50%) translateY(12px)}}
    .nav{position:fixed;top:0;left:0;right:0;z-index:100;padding:.6rem 1.5rem;background:rgba(0,15,40,.95);transform:translateY(-100%);transition:transform .3s;display:flex;align-items:center;justify-content:space-between}
    .nav.vis{transform:translateY(0)}.nav a{display:flex;align-items:center;gap:.5rem;color:#fff;text-decoration:none;font-weight:700;font-size:.95rem}.nav img{height:28px}
    .nums{background:var(--pr);color:#fff;padding:3.5rem 1rem}.nums-r{max-width:800px;margin:0 auto;display:flex;justify-content:space-around;text-align:center;flex-wrap:wrap}
    .num{font-size:clamp(2.5rem,5vw,3.5rem);font-weight:800;line-height:1}.num-l{font-size:.85rem;opacity:.6;margin-top:.3rem}
    .mosaic{display:flex;flex-wrap:wrap;gap:4px}.mosaic img{flex:1 1 24%;min-width:150px;height:250px;object-fit:cover;transition:transform .4s}.mosaic img:hover{transform:scale(1.05)}
    .vals{padding:5rem 1rem;background:#f8f9fa}.vals-r{max-width:1000px;margin:0 auto;display:flex;gap:2rem;flex-wrap:wrap;justify-content:center}
    .val{text-align:center;padding:2rem 1.5rem;flex:1;min-width:250px}.val-i{font-size:3rem;margin-bottom:1rem}.val-t{font-size:1.05rem;color:#555;line-height:1.6}
    .cta{background:linear-gradient(135deg,#0d1642,var(--pr));color:#fff;padding:5rem 1rem;text-align:center}.cta h2{font-size:clamp(2rem,5vw,3rem);font-weight:800;margin-bottom:1.5rem}
    .foot{background:#0a0a1a;color:rgba(255,255,255,.5);padding:1.5rem;text-align:center;font-size:.85rem}.foot a{color:rgba(255,255,255,.7)}
    .lbk{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:200;opacity:0;pointer-events:none;transition:opacity .3s}.lbk.open{opacity:1;pointer-events:auto}
    .lpn{position:fixed;top:0;right:0;bottom:0;width:380px;max-width:90vw;background:#fff;z-index:201;transform:translateX(100%);transition:transform .35s;padding:2rem;overflow-y:auto;box-shadow:-4px 0 30px rgba(0,0,0,.2)}
    .lpn.open{transform:translateX(0)}.lpn-x{position:absolute;top:1rem;right:1rem;background:none;border:none;font-size:1.5rem;cursor:pointer;color:#666}
    .lpn h3{font-size:1.4rem;font-weight:700;margin-bottom:1.5rem}
    .lpn input[type=text],.lpn input[type=password]{width:100%;padding:.7rem 1rem;border:1px solid #ddd;border-radius:8px;font-size:1rem;margin-bottom:1rem}
    .lpn input:focus{outline:none;border-color:var(--pr)}
    .lpn .lbtn{width:100%;padding:.7rem;border:none;border-radius:8px;background:var(--pr);color:#fff;font-weight:600;font-size:1rem;cursor:pointer}.lpn .lbtn:hover{background:#004080}
    .lpn label{font-size:.85rem}.lpn a{font-size:.85rem;color:var(--pr)}
    .lang{position:absolute;top:1rem;right:1rem;z-index:3}.lang a{color:rgba(255,255,255,.6);text-decoration:none;font-size:.75rem;padding:.2rem .4rem;border-radius:3px;text-transform:uppercase}
    .lang a:hover,.lang a.act{color:#fff;background:rgba(255,255,255,.2)}
    @media(max-width:768px){.nums-r{gap:1.5rem}.mosaic img{flex:1 1 45%;height:180px}.vals-r{flex-direction:column}}
    </style>
</head>
<body>

<nav class="nav" id="nav">
    <a href="#hero"><img src="images/club-logo.png" alt="CEP"> <?php echo $clubNameRaw; ?></a>
    <button onclick="openL()" class="btn btn-a" style="padding:.4rem 1.2rem;font-size:.85rem"><?php echo tr('login'); ?></button>
</nav>

<section class="hero" id="hero">
    <div class="hero-bg">
        <img src="images/landing/photo1.jpg" class="active">
        <img src="images/landing/photo2.jpg">
        <img src="images/landing/photo3.jpg">
        <img src="images/landing/photo4.jpg">
        <img src="images/landing/photo5.jpg">
        <img src="images/landing/photo6.jpg">
    </div>
    <div class="lang">
        <?php foreach ($supported as $l) { ?>
            <a href="?lang=<?php echo $l; ?>" class="<?php echo $l === $lang ? 'act' : ''; ?>"><?php echo $l; ?></a>
        <?php } ?>
    </div>
    <div class="hero-c">
        <img src="images/logo_CEP_large.png" height="160" style="filter:drop-shadow(0 0 12px rgba(255,255,255,.9)) drop-shadow(0 0 30px rgba(255,255,255,.5));margin-bottom:1rem" alt="CEP">
        <h1><?php echo $clubNameRaw; ?></h1>
        <p><?php echo tr('tagline'); ?> &#x1F93F;<br><span style="font-size:.85em;opacity:.7">16 <?php echo tr('nationalities'); ?> &middot; 2 <?php echo tr('federations'); ?></span></p>
        <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap">
            <button onclick="openL()" class="btn btn-a"><?php echo tr('login'); ?></button>
            <a href="mailto:<?php echo $clubEmail; ?>" class="btn btn-o"><?php echo tr('try_diving'); ?></a>
        </div>
    </div>
    <div class="scroll-d">&darr;</div>
</section>

<section class="nums">
    <div class="nums-r" id="numR">
        <div><div class="num" data-t="100">0</div><div class="num-l"><?php echo tr('members'); ?></div></div>
        <div><div class="num" data-t="16">0</div><div class="num-l"><?php echo tr('nationalities'); ?></div></div>
        <div><div class="num" data-t="1972">1972</div><div class="num-l"><?php echo tr('founded'); ?></div></div>
        <div><div class="num" data-t="35" data-s="%">0</div><div class="num-l"><?php echo tr('women'); ?></div></div>
    </div>
</section>

<div class="mosaic">
    <img src="images/landing/photo1.jpg" loading="lazy">
    <img src="images/landing/photo2.jpg" loading="lazy">
    <img src="images/landing/photo3.jpg" loading="lazy">
    <img src="images/landing/photo4.jpg" loading="lazy">
    <img src="images/landing/photo5.jpg" loading="lazy">
    <img src="images/landing/photo6.jpg" loading="lazy">
</div>

<section class="vals">
    <div class="vals-r">
        <div class="val"><div class="val-i">&#x1F93F;</div><div class="val-t"><?php echo tr('val1'); ?></div></div>
        <div class="val"><div class="val-i">&#x1F30D;</div><div class="val-t"><?php echo tr('val2'); ?></div></div>
        <div class="val"><div class="val-i">&#x1F4C5;</div><div class="val-t"><?php echo tr('val3'); ?></div></div>
    </div>
</section>

<section class="cta">
    <h2><?php echo tr('ready'); ?></h2>
    <a href="mailto:<?php echo $clubEmail; ?>" class="btn btn-a" style="font-size:1.1rem;padding:1rem 3rem"><?php echo tr('contact_us'); ?> &rarr;</a>
</section>

<footer class="foot">
    <?php echo $clubNameRaw; ?> &mdash; Luxembourg &middot; <a href="mailto:<?php echo $clubEmail; ?>"><?php echo $clubEmail; ?></a>
    <br>&copy; <?php echo date('Y'); ?>
</footer>

<div class="lbk" id="lbk" onclick="closeL()"></div>
<div class="lpn" id="lpn">
    <button class="lpn-x" onclick="closeL()">&times;</button>
    <h3><?php echo tr('login'); ?></h3>
    <form method="GET" action="/joomla/index.php">
        <input type="hidden" name="option" value="com_user">
        <input type="hidden" name="view" value="login">
        <button type="submit" class="lbtn" style="margin-bottom:1rem"><?php echo tr('login'); ?> &rarr;</button>
    </form>
    <p style="text-align:center;color:#888;font-size:.85rem"><?php echo tr('username'); ?> / <?php echo tr('password'); ?></p>
</div>

<script>
function openL(){document.getElementById('lbk').className='lbk open';document.getElementById('lpn').className='lpn open';document.body.style.overflow='hidden';}
function closeL(){document.getElementById('lbk').className='lbk';document.getElementById('lpn').className='lpn';document.body.style.overflow='';}
document.onkeydown=function(e){if(e.keyCode===27)closeL();};
var nav=document.getElementById('nav');
window.onscroll=function(){nav.className=window.pageYOffset>window.innerHeight*0.5?'nav vis':'nav';};
var imgs=document.querySelectorAll('.hero-bg img'),cur=0;
if(imgs.length>1)setInterval(function(){imgs[cur].className='';cur=(cur+1)%imgs.length;imgs[cur].className='active';},6000);
var counted=false;window.addEventListener('scroll',function(){if(counted)return;var r=document.getElementById('numR');if(!r)return;var rect=r.getBoundingClientRect();if(rect.top<window.innerHeight){counted=true;var els=document.querySelectorAll('.num');for(var i=0;i<els.length;i++){(function(el){var tgt=parseInt(el.getAttribute('data-t')),suf=el.getAttribute('data-s')||'',c=0,step=Math.max(1,Math.ceil(tgt/40));var tm=setInterval(function(){c+=step;if(c>=tgt){c=tgt;clearInterval(tm);}el.textContent=c+suf;},30);})(els[i]);}}});
</script>
</body>
</html>
