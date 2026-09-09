<%-- The Menus section landing view: every menu as a tile. --%>
<div class="menu-admin__grid">
    <% if $Menus %>
        <% loop $Menus %>
            <a
                href="$CMSEditLink"
                class="menu-tile<% if $HasDraftChanges %> menu-tile--draft<% end_if %>"
                aria-label="<%t Heyday\MenuManager\MenuAdmin.EDIT_MENU 'Edit {title}' title=$Title.ATT %>"
            >
                <span class="menu-tile__header">
                    <span class="menu-tile__icon font-icon-menu" aria-hidden="true"></span>
                    <span class="menu-tile__heading">
                        <span class="menu-tile__title">$Title.XML</span>
                        <span class="menu-tile__name">$Name.XML</span>
                    </span>
                    <% if $HasDraftChanges %>
                        <span
                            class="menu-tile__status"
                            title="<%t Heyday\MenuManager\MenuAdmin.HAS_DRAFT 'Has changes that are not published' %>"
                        >
                            <span class="sr-only"><%t Heyday\MenuManager\MenuAdmin.HAS_DRAFT 'Has changes that are not published' %></span>
                        </span>
                    <% end_if %>
                </span>

                <span class="menu-tile__count">
                    <%t Heyday\MenuManager\MenuAdmin.LINK_COUNT '{count} links' count=$MenuItemCount %>
                </span>

                <% if $Description %>
                    <span class="menu-tile__description">$Description.XML</span>
                <% end_if %>
            </a>
        <% end_loop %>
    <% else %>
        <p class="menu-admin__empty">
            <%t Heyday\MenuManager\MenuAdmin.NO_MENUS 'There are no menus yet. Add one to get started.' %>
        </p>
    <% end_if %>
</div>
