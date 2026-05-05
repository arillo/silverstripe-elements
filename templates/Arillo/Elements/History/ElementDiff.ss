<div class="element-diff element-diff--$status">
    <header class="element-diff__header">
        <span class="element-diff__badge element-diff__badge--$status">
            <% if $status == 'added' %><%t Arillo\\Elements\\History.Added 'Added' %>
            <% else_if $status == 'removed' %><%t Arillo\\Elements\\History.Removed 'Removed' %>
            <% else_if $status == 'modified' %><%t Arillo\\Elements\\History.Modified 'Modified' %>
            <% else_if $status == 'error' %><%t Arillo\\Elements\\History.Error 'Error' %>
            <% else_if $status == 'depth_limit' %><%t Arillo\\Elements\\History.DepthLimit 'Depth limit reached' %>
            <% else %>$status<% end_if %>
        </span>
        <span class="element-diff__summary">$elementSummary.RAW</span>
    </header>
    <% if $fieldChanges %>
        <div class="element-diff__fields">
            <% loop $fieldChanges %>
                <% include Arillo/Elements/History/FieldDiff %>
            <% end_loop %>
        </div>
    <% end_if %>
    <% if $childChanges %>
        <ul class="element-diff__children">
            <% loop $childChanges %>
                <li class="element-diff__child">
                    <% include Arillo/Elements/History/ElementDiff %>
                </li>
            <% end_loop %>
        </ul>
    <% end_if %>
    <% if $errorMessage %>
        <div class="element-diff__error">$errorMessage</div>
    <% end_if %>
</div>
