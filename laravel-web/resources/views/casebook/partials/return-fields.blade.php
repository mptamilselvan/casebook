<input type="hidden" name="filter" value="{{ $filter }}">
<input type="hidden" name="tab" :value="tab">
@if (!empty($active))
    <input type="hidden" name="case" value="{{ $active->id }}">
@endif
@if (!empty($activeTemplate))
    <input type="hidden" name="template" value="{{ $activeTemplate->id }}">
@endif
