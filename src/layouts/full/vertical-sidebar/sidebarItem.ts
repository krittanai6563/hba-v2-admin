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

  const currentDate = new Date().getDate();

  const timeRestrictedMenus: Menu[] = [
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
  ];

  
  const userGuideMenu: Menu = {
    title: 'คู่มือการกรอกข้อมูล',
    icon: 'notebook-minimalistic-line-duotone',
    to: 'https://uat.hba-sales.org/%E0%B8%84%E0%B8%B9%E0%B9%88%E0%B8%A1%E0%B8%B7%E0%B8%AD%E0%B9%83%E0%B8%8A%E0%B9%89%E0%B8%87%E0%B8%B2%E0%B8%99%E0%B8%A3%E0%B8%B0%E0%B8%9A%E0%B8%9A%E0%B8%81%E0%B8%A3%E0%B8%AD%E0%B8%81%E0%B8%82%E0%B9%89%E0%B8%AD%E0%B8%A1%E0%B8%B9%E0%B8%A5%20%E0%B8%AA%E0%B8%B3%E0%B8%AB%E0%B8%A3%E0%B8%B1%E0%B8%9A%E0%B8%AA%E0%B8%A1%E0%B8%B2%E0%B8%8A%E0%B8%B4%E0%B8%81.pdf',
    external: true, 
  };

  
  const shouldShowTimeRestrictedMenus = role !== 'admin' || (role === 'admin' && currentDate >= 11);

  return [
    { header: 'Home' },
  
    ...(shouldShowTimeRestrictedMenus ? timeRestrictedMenus : []),

    ...(role !== 'admin' && role !== 'master' ? [userGuideMenu] : []),

    ...(role === 'admin'
      ? [
          {
            title: 'จัดการข้อมูลผู้ใช้งาน',
            icon: 'user-plus-linear',
            to: '/ui/user',
            external: false,
          },
           {
            title: 'คู่มือการกรอกข้อมูล',
            icon: 'notebook-minimalistic-line-duotone',
            to: 'https://uat.hba-sales.org/%E0%B8%84%E0%B8%B9%E0%B9%88%E0%B8%A1%E0%B8%B7%E0%B8%AD%E0%B9%83%E0%B8%8A%E0%B9%89%E0%B8%87%E0%B8%B2%E0%B8%99%E0%B8%A3%E0%B8%B0%E0%B8%9A%E0%B8%9A%E0%B8%81%E0%B8%A3%E0%B8%AD%E0%B8%81%E0%B8%82%E0%B9%89%E0%B8%AD%E0%B8%A1%E0%B8%B9%E0%B8%A5%20%E0%B8%AA%E0%B8%B3%E0%B8%AB%E0%B8%A3%E0%B8%B1%E0%B8%9A%E0%B8%9C%E0%B8%B9%E0%B9%89%E0%B8%94%E0%B8%B9%E0%B9%81%E0%B8%A5%E0%B8%AA%E0%B8%A1%E0%B8%B2%E0%B8%84%E0%B8%A1.pdf',
            external: true,
          },
        ]
      : []),

    
    ...(role === 'master'
      ? [
          {
            title: 'ข้อมูลสมาชิก',
            icon: 'shield-user-outline',
            to: '/ui/report_user',
            external: false,
          },
          {
           title: 'สรุปรายงาน',
           icon: 'notebook-line-duotone',
           to: '/ui/repost_admin',
           external: false,
           }, {
           title: 'สรุปรายงาน hba',
           icon: 'notebook-line-duotone',
           to: '/ui/repost_hba',
           external: false,
           },
           {
            title: 'คู่มือการกรอกข้อมูล',
            icon: 'notebook-minimalistic-line-duotone',
            to: 'https://uat.hba-sales.org/%E0%B8%84%E0%B8%B9%E0%B9%88%E0%B8%A1%E0%B8%B7%E0%B8%AD%E0%B9%83%E0%B8%8A%E0%B9%89%E0%B8%87%E0%B8%B2%E0%B8%99%E0%B8%A3%E0%B8%B0%E0%B8%9A%E0%B8%9A%E0%B8%81%E0%B8%A3%E0%B8%AD%E0%B8%81%E0%B8%82%E0%B9%89%E0%B8%AD%E0%B8%A1%E0%B8%B9%E0%B8%A5%20%E0%B8%AA%E0%B8%B3%E0%B8%AB%E0%B8%A3%E0%B8%B1%E0%B8%9A%E0%B8%9C%E0%B8%B9%E0%B9%89%E0%B8%94%E0%B8%B9%E0%B9%81%E0%B8%A5%20wis.pdf',
            external: true,
          },
        ]
      : []),
  ];
}