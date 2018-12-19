<div class="element-summary">
  <% if $Image.Exists %>
    <div class="element-summary-img">
      $Image.Pad(70,70)
    </div>
  <% end_if %>

  <div class="element-summary-content">
    <div class="element-summary-title">$Title</div>

    <% if $Content %>
      <div class="element-summary-txt">$Content</div>
    <% end_if %>
  </div>
</div>

