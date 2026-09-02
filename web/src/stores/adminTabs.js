import { defineStore } from 'pinia'

const fixedTab = { title: '工作台', path: '/admin', fixed: true }
const storageKey = 'moo_passport_admin_tabs'
function restore() {
  try {
    const items = JSON.parse(localStorage.getItem(storageKey) || '[]')
    return [fixedTab, ...(Array.isArray(items) ? items.filter((item) => item?.title && item?.path && item.path !== '/admin') : [])]
  } catch { return [fixedTab] }
}
export const useAdminTabsStore = defineStore('admin-tabs', {
  state: () => ({ opened: restore(), refreshKey: 0 }),
  actions: {
    persist() { localStorage.setItem(storageKey, JSON.stringify(this.opened)) },
    prune(canAccessPath) {
      this.opened = this.opened.filter((item) => canAccessPath(item.path))
      this.persist()
    },
    add(route) {
      if (!route.meta?.title || !route.path.startsWith('/admin')) return
      if (!this.opened.some((item) => item.path === route.path)) this.opened.push({ title: route.meta.title, path: route.path, fixed: route.path === '/admin' })
      this.persist()
    },
    remove(path) { this.opened = this.opened.filter((item) => item.fixed || item.path !== path); this.persist() },
    removeLeft(path) {
      const index = this.opened.findIndex((item) => item.path === path)
      this.opened = this.opened.filter((item, itemIndex) => item.fixed || itemIndex >= index)
      this.persist()
    },
    removeRight(path) {
      const index = this.opened.findIndex((item) => item.path === path)
      this.opened = this.opened.filter((item, itemIndex) => item.fixed || itemIndex <= index)
      this.persist()
    },
    closeOthers(path) { this.opened = this.opened.filter((item) => item.fixed || item.path === path); this.persist() },
    closeAll() { this.opened = [fixedTab]; this.persist() },
    refresh() { this.refreshKey += 1 },
  },
})
