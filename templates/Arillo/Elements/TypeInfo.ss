<% if $Icon %>
  <div class="element-typeInfo">
    <div class="element-typeInfo-icon">
      <i class="element-icon $Icon"></i>
    </div>

    <div class="element-typeInfoIcon">
      $Type
      <% if $IsVersioned && $VersionState %>
        <br>
        <span class="ss-gridfield-badge badge status-{$VersionState}" title="$VersionStateTitle"></span>
      <% end_if %>
    </div>
  </div>
<% end_if %>
