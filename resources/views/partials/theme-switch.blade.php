{{-- Light / dark / device theme switch. Needs JavaScript (public/js/theme.js applies the choice, nsp.js wires the buttons), so it is hidden without it. --}}
<div class="theme-switch" role="group" aria-label="Colour theme" data-theme-switch>
    <button type="button" data-theme-choice="system" aria-pressed="true" title="Use device setting"><i class="fas fa-circle-half-stroke" aria-hidden="true"></i><span class="visually-hidden">Use device setting</span></button>
    <button type="button" data-theme-choice="light" aria-pressed="false" title="Light theme"><i class="fas fa-sun" aria-hidden="true"></i><span class="visually-hidden">Light theme</span></button>
    <button type="button" data-theme-choice="dark" aria-pressed="false" title="Dark theme"><i class="fas fa-moon" aria-hidden="true"></i><span class="visually-hidden">Dark theme</span></button>
</div>
