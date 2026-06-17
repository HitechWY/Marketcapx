@extends('admin.layouts.app')
@section('panel')
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive--sm table-responsive">
                        <table class="table table--light style--two">
                            <thead>
                                <tr>
                                    <th>@lang('SL')</th>
                                    <th>@lang('Name')</th>
                                    <th>@lang('Is Delivery')</th>
                                    <th>@lang('Status')</th>
                                    <th>@lang('Action')</th>
                                </tr>
                            </thead>
                            <tbody class="list">
                                @forelse ($categories as $category)
                                    <tr>
                                        <td>{{ $categories->firstItem() + $loop->index }}</td>
                                        <td>{{ __($category->name) }}</td>
                                        <td>
                                            @if ($category->is_delivery)
                                                <span class="badge badge--primary"> @lang('Yes')</span>
                                            @else
                                                <span class="badge badge--warning"> @lang('No')</span>
                                            @endif
                                        </td>
                                        <td>
                                            @php
                                                echo $category->statusBadge;
                                            @endphp
                                        </td>

                                        <td>
                                            <div class="button--group">
                                                <button type="button" class="btn btn-sm btn-outline--primary editBtn" data-resource="{{ $category }}" data-modal_title="@lang('Update Category')">
                                                    <i class="la la-pencil"></i>@lang('Edit')
                                                </button>
                                                @if ($category->status == Status::DISABLE)
                                                    <button type="button" class="btn btn-sm btn-outline--success confirmationBtn" data-action="{{ route('admin.category.status', $category->id) }}" data-question="@lang('Are you sure to enable this category?')">
                                                        <i class="la la-eye"></i>@lang('Enable')
                                                    </button>
                                                @else
                                                    <button type="button" class="btn btn-sm btn-outline--danger confirmationBtn" data-action="{{ route('admin.category.status', $category->id) }}" data-question="@lang('Are you sure to disable this category?')">
                                                        <i class="la la-eye-slash"></i>@lang('Disable')
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-muted text-center" colspan="100%">{{ __($emptyMessage) }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if ($categories->hasPages())
                    <div class="card-footer py-4">
                        {{ paginateLinks($categories) }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Create Update Modal -->
    <div class="modal fade" id="categoryModal">
        <div class="modal-dialog" role="document">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title"></h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="las la-times"></i>
                    </button>
                </div>
                <form action="{{ route('admin.category.store') }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label>@lang('Name')</label>
                            <input type="text" class="form-control" name="name">
                        </div>

                        <div class="form-group">
                            <label>@lang('Delivery Opportunity')</label>
                            <select class="select2 form-control" name="is_delivery" data-minimum-results-for-search="-1">
                                <option value="0">@lang('No')</option>
                                <option value="1">@lang('Yes')</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn--primary w-100 h-45">@lang('Submit')</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <x-confirmation-modal />
@endsection

@push('script')
    <script>
        (function($) {
            "use strict";

            let modal = $('#categoryModal');
            let action = `{{ route('admin.category.store') }}`;

            $('.addBtn').on('click', function() {
                modal.find('.modal-title').text($(this).data('modal_title'));
                modal.find('form')[0].reset();
                modal.find('form').attr('action', action);
                modal.modal('show');
            });

            $('.editBtn').on('click', function() {
                let data = $(this).data('resource');
                modal.find('.modal-title').text($(this).data('modal_title'));
                modal.find('input[name=name]').val(data.name);
                modal.find('select[name=is_delivery]').val(data.is_delivery).change();
                modal.find('form').attr('action', ` ${action}/${data.id}`);
                modal.modal('show');
            });

        })(jQuery);
    </script>
@endpush

@push('breadcrumb-plugins')
    <x-search-form />
    <button type="button" class="btn btn-sm btn-outline--primary me-2 h-45 addBtn" data-modal_title="@lang('Add New')">
        <i class="las la-plus"></i>@lang('Add New')
    </button>
@endpush
