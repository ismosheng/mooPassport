/**
 * Naive UI 全局主题约束。
 * 页面不得自行覆盖品牌色和基础字号，特殊业务状态色除外。
 */
export const themeOverrides = {
  common: {
    primaryColor: '#2c82ff',
    primaryColorHover: '#4b94ff',
    primaryColorPressed: '#1769d2',
    primaryColorSuppl: '#2c82ff',
    infoColor: '#2c82ff',
    successColor: '#18a058',
    warningColor: '#f0a020',
    errorColor: '#d03050',
    textColorBase: '#1f2329',
    textColor1: '#1f2329',
    textColor2: '#4e5969',
    textColor3: '#86909c',
    bodyColor: '#f5f6f8',
    cardColor: '#ffffff',
    borderColor: '#e5e6eb',
    dividerColor: '#e5e6eb',
    borderRadius: '6px',
    borderRadiusSmall: '4px',
    fontSize: '14px',
    fontSizeMini: '12px',
    fontSizeTiny: '12px',
    fontSizeSmall: '13px',
    fontSizeMedium: '14px',
    fontSizeLarge: '16px',
    heightMedium: '36px',
    heightLarge: '40px',
  },
  Button: {
    borderRadiusMedium: '6px',
    borderRadiusLarge: '8px',
    fontWeight: '500',
    heightMedium: '36px',
    heightLarge: '40px',
  },
  Input: {
    borderRadius: '8px',
    heightMedium: '36px',
    heightLarge: '40px',
    boxShadowFocus: '0 0 0 2px rgba(44, 130, 255, 0.14)',
  },
  Alert: { borderRadius: '6px' },
  Card: { borderRadius: '8px' },
}
