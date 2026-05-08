<div class="elements-history-field elements-history-field--$Mode">
    <% if $Mode == 'compare' && $DiffTree %>
        <% if $DiffTree.reorder %>
            <div class="elements-history-field__reorder-banner">
                <%t Arillo\\Elements\\History.Reordered 'Elements have been reordered' %>
            </div>
        <% end_if %>
        <ul class="elements-history-field__items">
            <% loop $DiffTree.changes %>
                <li class="elements-history-field__item elements-history-field__item--$status">
                    <% include Arillo/Elements/History/ElementDiff %>
                </li>
            <% end_loop %>
        </ul>
    <% else_if $Items %>
        <ul class="elements-history-field__items">
            <% loop $Items %>
                <li class="elements-history-field__item">
                    <h4>$Title</h4>
                    <div class="elements-history-field__summary">$Summary.RAW</div>
                </li>
            <% end_loop %>
        </ul>
    <% end_if %>
</div>
