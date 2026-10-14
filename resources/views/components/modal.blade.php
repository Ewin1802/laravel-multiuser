
<div
id="{{ $id }}"
class="modal">

    <div class="modal-overlay"></div>

    <div class="modal-content">

        <div class="modal-header">

            <div class="modal-title">

                {{ $title }}

            </div>

            <button class="modal-close">

                <i data-lucide="x"></i>

            </button>

        </div>

        <div class="modal-body">

            {{ $slot }}

        </div>

        <div class="modal-footer">

            {{ $footer ?? '' }}

        </div>

    </div>

</div>
