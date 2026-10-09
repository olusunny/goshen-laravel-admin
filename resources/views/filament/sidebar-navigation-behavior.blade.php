<script>
    (() => {
        if (window.goshenAdminNavigationEnhancerLoaded) return
        window.goshenAdminNavigationEnhancerLoaded = true

        const normalize = value => (value || '').toString().toLowerCase().replace(/\s+/g, ' ').trim()
        const groups = () => Array.from(document.querySelectorAll('.fi-sidebar-group[data-group-label]'))
        const sidebarStore = () => window.Alpine?.store('sidebar')
        let collapsedBeforeSearch = null

        const restoreGroups = () => {
            if (collapsedBeforeSearch !== null && sidebarStore()) {
                sidebarStore().collapsedGroups = collapsedBeforeSearch
                collapsedBeforeSearch = null
            }
        }

        const filterMenu = () => {
            const query = normalize(document.querySelector('[data-goshen-menu-search]')?.value)
            const store = sidebarStore()
            if (query && collapsedBeforeSearch === null && Array.isArray(store?.collapsedGroups)) {
                collapsedBeforeSearch = [...store.collapsedGroups]
            }
            if (!query) restoreGroups()
            let visibleItems = 0
            document.querySelector('.fi-sidebar')?.classList.toggle('goshen-searching', !!query)
            groups().forEach(group => {
                const groupLabelMatches = normalize(group.dataset.groupLabel).includes(query)
                let groupHasMatches = false
                group.querySelectorAll('.fi-sidebar-item').forEach(item => {
                    const label = normalize(item.querySelector('.fi-sidebar-item-label')?.textContent)
                    const matches = !query || groupLabelMatches || label.includes(query)
                    item.classList.toggle('goshen-nav-hidden', !matches)
                    if (matches) {
                        groupHasMatches = true
                        visibleItems++
                    }
                })
                group.classList.toggle('goshen-nav-hidden', !!query && !groupHasMatches)
                if (query && groupHasMatches && store?.groupIsCollapsed(group.dataset.groupLabel)) {
                    store.toggleCollapsedGroup(group.dataset.groupLabel)
                }
            })
            const status = document.querySelector('[data-goshen-menu-search-status]')
            if (status) status.textContent = !query ? '' : visibleItems
                ? `${visibleItems} menu item${visibleItems === 1 ? '' : 's'} shown.`
                : 'No matching menu items.'
        }

        const bind = () => {
            const input = document.querySelector('[data-goshen-menu-search]')
            if (!input || input.dataset.goshenMenuSearchBound === 'true') return
            input.dataset.goshenMenuSearchBound = 'true'
            input.addEventListener('input', filterMenu)
            filterMenu()
        }
        document.addEventListener('DOMContentLoaded', bind)
        document.addEventListener('alpine:initialized', bind)
        document.addEventListener('livewire:navigate', restoreGroups)
        document.addEventListener('livewire:navigated', bind)
        setTimeout(bind, 80)
    })()
</script>
