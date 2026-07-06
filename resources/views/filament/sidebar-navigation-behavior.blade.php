<script>
    (() => {
        if (window.goshenAdminNavigationEnhancerLoaded) {
            return
        }

        window.goshenAdminNavigationEnhancerLoaded = true

        const collapseVersionKey = 'goshenSidebarCollapsedDefaults:v1'
        const collapsedGroupsKey = 'collapsedGroups'

        const normalize = (value) => (value || '')
            .toString()
            .toLowerCase()
            .replace(/\s+/g, ' ')
            .trim()

        const groups = () => Array.from(document.querySelectorAll('.fi-sidebar-group[data-group-label]'))

        const collapseDefaults = () => {
            const allGroups = groups()

            if (!allGroups.length || localStorage.getItem(collapseVersionKey)) {
                return
            }

            const collapsedLabels = allGroups
                .filter((group) => !group.classList.contains('fi-active'))
                .map((group) => group.dataset.groupLabel)
                .filter(Boolean)

            localStorage.setItem(collapsedGroupsKey, JSON.stringify(collapsedLabels))
            localStorage.setItem(collapseVersionKey, '1')

            allGroups.forEach((group) => {
                if (!collapsedLabels.includes(group.dataset.groupLabel)) {
                    return
                }

                group.classList.add('fi-collapsed')
                group.querySelector('.fi-sidebar-group-items')?.style.setProperty('display', 'none')
            })
        }

        const filterMenu = () => {
            const input = document.querySelector('[data-goshen-menu-search]')
            const sidebar = document.querySelector('.fi-sidebar')
            const query = normalize(input?.value)

            sidebar?.classList.toggle('goshen-searching', query.length > 0)

            groups().forEach((group) => {
                const groupLabel = normalize(group.querySelector('.fi-sidebar-group-label')?.textContent)
                let groupMatches = query.length === 0 || groupLabel.includes(query)

                group.querySelectorAll('.fi-sidebar-item').forEach((item) => {
                    const itemLabel = normalize(item.querySelector('.fi-sidebar-item-label')?.textContent)
                    const itemMatches = query.length === 0 || groupMatches || itemLabel.includes(query)

                    item.classList.toggle('goshen-nav-hidden', !itemMatches)

                    if (itemMatches && query.length > 0) {
                        groupMatches = true
                    }
                })

                group.classList.toggle('goshen-nav-hidden', query.length > 0 && !groupMatches)

                const list = group.querySelector('.fi-sidebar-group-items')

                if (!list) {
                    return
                }

                if (query.length > 0 && groupMatches) {
                    list.style.display = 'grid'
                    group.classList.remove('fi-collapsed')
                } else if (query.length === 0) {
                    list.style.removeProperty('display')
                }
            })
        }

        const bind = () => {
            collapseDefaults()
            filterMenu()

            document
                .querySelector('[data-goshen-menu-search]')
                ?.addEventListener('input', filterMenu)
        }

        document.addEventListener('DOMContentLoaded', bind)
        document.addEventListener('livewire:navigated', bind)
        setTimeout(bind, 80)
    })()
</script>
