<% if $Icon %>
  <i class="$Icon"></i> $Type
  <% if $IsVersioned && $VersionState %>
    <br>
    <span class="ss-gridfield-badge badge status-{$VersionState}" title="$VersionStateTitle"></span>
  <% end_if %>
<% end_if %>

