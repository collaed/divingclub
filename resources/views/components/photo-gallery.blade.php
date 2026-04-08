{{--
    Fullscreen photo gallery component.
    Usage: @include('components.photo-gallery', ['galleryId' => 'main'])
    Then call openGallery('main', index) from JS.
    Photos are loaded via data attribute on the container.
--}}
<div class="pg-overlay" id="pg-{{ $galleryId ?? 'main' }}" onclick="if(event.target===this)closeGallery()">
    <button class="pg-close" onclick="closeGallery()">✕</button>
    <button class="pg-prev" onclick="pgNav(-1)">‹</button>
    <img class="pg-img" id="pg-img-{{ $galleryId ?? 'main' }}" src="" alt="">
    <button class="pg-next" onclick="pgNav(1)">›</button>
    <div class="pg-counter" id="pg-counter-{{ $galleryId ?? 'main' }}"></div>
</div>

@once
<style>
.pg-overlay { position:fixed;inset:0;z-index:500;background:rgba(0,0,0,.92);display:none;align-items:center;justify-content:center; }
.pg-overlay.open { display:flex; }
.pg-img { max-width:92vw;max-height:88vh;object-fit:contain;border-radius:4px;user-select:none; }
.pg-close { position:absolute;top:1rem;right:1.5rem;background:none;border:none;color:#fff;font-size:2rem;cursor:pointer;z-index:501; }
.pg-prev,.pg-next { position:absolute;top:50%;transform:translateY(-50%);background:rgba(255,255,255,.15);border:none;color:#fff;font-size:2.5rem;padding:.5rem 1rem;cursor:pointer;border-radius:8px; }
.pg-prev { left:1rem; } .pg-next { right:1rem; }
.pg-prev:hover,.pg-next:hover { background:rgba(255,255,255,.3); }
.pg-counter { position:absolute;bottom:1.5rem;left:50%;transform:translateX(-50%);color:rgba(255,255,255,.6);font-size:.9rem; }
</style>
<script>
let pgPhotos=[],pgIdx=0,pgEl=null,pgImg=null,pgCounter=null;
function openGallery(id,idx){
    pgEl=document.getElementById('pg-'+id);
    pgImg=document.getElementById('pg-img-'+id);
    pgCounter=document.getElementById('pg-counter-'+id);
    pgPhotos=JSON.parse(pgEl.dataset.photos||'[]');
    pgIdx=idx||0;pgShow();pgEl.classList.add('open');document.body.style.overflow='hidden';
}
function closeGallery(){if(pgEl){pgEl.classList.remove('open');document.body.style.overflow='';}}
function pgNav(d){pgIdx=(pgIdx+d+pgPhotos.length)%pgPhotos.length;pgShow();}
function pgShow(){if(pgImg&&pgPhotos[pgIdx]){pgImg.src=pgPhotos[pgIdx];pgCounter.textContent=(pgIdx+1)+' / '+pgPhotos.length;}}
document.addEventListener('keydown',e=>{if(!pgEl||!pgEl.classList.contains('open'))return;if(e.key==='Escape')closeGallery();if(e.key==='ArrowLeft')pgNav(-1);if(e.key==='ArrowRight')pgNav(1);});
</script>
@endonce
