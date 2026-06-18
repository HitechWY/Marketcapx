{{-- UI Auth card (rebuild R4).
     The user-auth account-card shell (image panel + form panel). Emits the EXACT
     existing classes (.account-wrapper > .left.bg_img + .right > .inner) that
     material-auth.css styles, so it is visually inert — a markup de-duplication
     for the user login/register/forgot/reset views. Everything inside .inner
     (heading, social login, the FROZEN form, footer links) is passed through the
     slot untouched. Presentation only; owns no form, route, or CMS binding.
     NOTE: only the user-guard auth views share this shell. Agent/merchant auth
     use a different shell (.account-section, common_auth layout) and are NOT
     adopted here. --}}
@props(['image' => null])
<div class="account-wrapper">
    <div class="left bg_img" style="background-image: url('{{ $image }}');">
    </div>
    <div class="right">
        <div class="inner">
            {{ $slot }}
        </div>
    </div>
</div>
