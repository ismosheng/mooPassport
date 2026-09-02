import { AppsOutline, GridOutline, KeyOutline, PeopleOutline, SettingsOutline, ShieldCheckmarkOutline } from '@vicons/ionicons5'

export const adminMenus = [
  { title: '控制台', children: [{ title: '工作台', path: '/admin', icon: GridOutline, permission: 'admin.dashboard.read' }] },
  { title: '接入管理', children: [{ title: 'OAuth 应用', path: '/admin/applications', icon: AppsOutline, permission: 'admin.applications.read' }] },
  { title: '账号与安全', children: [
    { title: '用户管理', path: '/admin/users', icon: PeopleOutline, permission: 'admin.users.read' },
    { title: '角色与权限', path: '/admin/roles', icon: KeyOutline, permission: 'admin.roles.read' },
    { title: '安全审计', path: '/admin/audit', icon: ShieldCheckmarkOutline, permission: 'admin.audit.read' },
  ] },
  { title: '系统', children: [{ title: '系统设置', path: '/admin/settings', icon: SettingsOutline, permission: 'admin.settings.read' }] },
]

export function firstAccessibleAdminPath(hasPermission) {
  return adminMenus.flatMap((group) => group.children)
    .find((item) => !item.pending && hasPermission(item.permission))?.path || '/account'
}
