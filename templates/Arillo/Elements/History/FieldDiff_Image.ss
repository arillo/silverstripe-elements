<div class="field-diff field-diff--has_one_image">
    <strong class="field-diff__label">$fieldLabel</strong>
    <div class="field-diff__images">
        <% if $oldValue.id %>
            <figure class="field-diff__image field-diff__image--old">
                <% if $oldValue.thumbnailUrl %>
                    <img src="$oldValue.thumbnailUrl" alt="$oldValue.filename">
                <% end_if %>
                <figcaption><del>$oldValue.filename</del></figcaption>
            </figure>
        <% end_if %>
        <% if $newValue.id %>
            <figure class="field-diff__image field-diff__image--new">
                <% if $newValue.thumbnailUrl %>
                    <img src="$newValue.thumbnailUrl" alt="$newValue.filename">
                <% end_if %>
                <figcaption><ins>$newValue.filename</ins></figcaption>
            </figure>
        <% end_if %>
    </div>
</div>
