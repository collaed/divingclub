<x-layout :title="__('Contact Us')">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h4 class="mb-4">{{ __('Contact Us') }}</h4>
            <div class="card dc-card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <h6>📧 {{ __('By Email') }}</h6>
                            <p><a href="mailto:clubcep@gmail.com">clubcep@gmail.com</a></p>

                            <h6 class="mt-4">📍 {{ __('By Post') }}</h6>
                            <p>Club Européen de Plongée<br>B.P. 1162<br>L-1011 Luxembourg</p>
                        </div>
                        <div class="col-md-6 mb-4">
                            <h6>🤿 {{ __('Training Locations') }}</h6>
                            <p class="mb-1"><strong>Piscine de Bonnevoie</strong></p>
                            <p class="small text-muted">Rue du Cimetière, L-1338 Luxembourg</p>
                            <p class="mb-1"><strong>Piscine de Mersch</strong></p>
                            <p class="small text-muted">Krounebierg, L-7572 Mersch</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layout>
