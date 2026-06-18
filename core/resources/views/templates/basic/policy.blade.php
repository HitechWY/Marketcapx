@extends('Template::layouts.frontend')
@section('content')
    <section class="pt-100 pb-100">
        <div class="container">
            <div class="row gy-4 justify-content-center">
                <div class="col-xl-12">
                    <x-ui.card variant="outlined" class="md-prose">
                        @php echo $policy->data_values->details; @endphp
                    </x-ui.card>
                </div>
            </div>
        </div>
    </section>
@endsection
