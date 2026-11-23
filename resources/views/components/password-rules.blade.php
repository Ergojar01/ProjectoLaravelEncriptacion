<ul class="rules" id="{{ $input }}_rules" data-pass-rules="{{ $input }}" data-min-length="{{ config('security.password.min_length') }}">
    <li data-rule="len">Mínimo {{ config('security.password.min_length') }} caracteres</li>
    <li data-rule="up">Una mayúscula</li>
    <li data-rule="low">Una minúscula</li>
    <li data-rule="num">Un número</li>
    <li data-rule="sym">Un símbolo</li>
    <li data-rule="match">La confirmación coincide</li>
</ul>
