export interface Menu {
  header?: string;
  title?: string;
  icon?: any;
  to?: string;
  chip?: string;
  chipColor?: string;
  chipBgColor?: string;
  chipVariant?: string;
  chipIcon?: string;
  children?: Menu[];
  disabled?: boolean;
  type?: string;
  subCaption?: string;
  external?: boolean;
}

export function getSidebarItems(role: string): Menu[] {
  return [
    { header: 'Home' },
    {
      title: 'ภาพรวมยอดเซ็นสัญญา',
      icon: 'home-add-linear',
      to: '/',
      external: false,
    },
    {
      title: 'ข้อมูลยอดเซ็นสัญญา',
      icon: 'clipboard-list-linear',
      to: '/ui/typography',
      external: false,
    },
    {
      title: 'รายงานแบ่งตามไตรมาส',
      icon: 'document-linear',
      to: '/ui/shadow',
      external: false,
    },
    {
      title: 'รายงานแบ่งตามมูลค่า',
      icon: 'wallet-money-linear',
      to: '/ui/house',
      external: false,
    },
    {
      title: 'รายงานแบ่งตามภูมิภาค',
      icon: 'wad-of-money-linear',
      to: '/ui/region',
      external: false,
    },
    ...(role === 'admin'
      ? [
          {
            title: 'จัดการข้อมูลผู้ใช้งาน',
            icon: 'user-plus-linear',
            to: '/ui/user',
            external: false,
          },
          {
            title: 'สรุปรายงาน',
            icon: 'notebook-line-duotone',
            to: '/ui/report',
            external: false,
          },
        ]
      : []),
    {
      title: 'คู่มือการกรอกข้อมูล',
      icon: 'notebook-minimalistic-line-duotone',
      to: 'https://materialpro-vue3-admin.vercel.app/dashboards/modern',
      external: true,
    },
  ];
}
