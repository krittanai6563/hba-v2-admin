const MainRoutes = {
    path: '/main',
    meta: {
        requiresAuth: true
    },
    redirect: '/main',
    component: () => import('@/layouts/full/FullLayout.vue'),
    children: [
        {
            name: 'Dashboard',
            path: '/',
            component: () => import('@/views/dashboard/index.vue'),
            beforeEnter: (to: any, from: any, next: any) => {
                const userJson = sessionStorage.getItem('user');
                const user = userJson ? JSON.parse(userJson) : null;
                const loginTime = user?.loginTime || 0;
                const now = Date.now();
                const sessionExpired = now - loginTime > 18_000_000;

                if (sessionExpired) {
                    sessionStorage.removeItem('user');
                    localStorage.removeItem('user_id');
                    return next({ name: 'Login' });
                }

                if (user) {
                    if (user.role === 'admin') {
                        next();
                    } else {
                        next();
                    }
                } else {
                    next({ name: 'Login' });
                }
            }
        },
        {
            name: 'record',
            path: '/record',
            component: () => import('@/views/dashboard/record.vue')
        },
        {
            name: 'Typography',
            path: '/ui/typography',
            component: () => import('@/views/components/Typography.vue')
        },
        {
            name: 'Shadow',
            path: '/ui/shadow',
            component: () => import('@/views/components/Shadow.vue')
        },
        {
            name: 'Icons',
            path: '/ui/house',
            component: () => import('@/views/components/house.vue')
        },
        {
            name: 'Starter',
            path: '/ui/region',
            component: () => import('@/views/components/region.vue')
        },
        { name: 'User',
          path:'/ui/user',
          component:() =>import('@/views/components/user.vue')
        },
        {
            name:'Reports',
            path:'/ui/report',
            component: () => import('@/views/components/report.vue')
        },
         {
            name:'Reports_user',
            path:'/ui/report_user',
            component: () => import('@/views/components/repost_user.vue')
        }, {
            name:'post_repost_user',
            path:'/ui/post_repost_user',
            component: () => import('@/views/components/MemberDetails.vue')
        }, {
            name:'repost_admin',
            path:'/ui/repost_admin',
            component: () => import('@/views/components/repost_admin.vue')
        }
        , {
            name:'repost_hba',
            path:'/ui/repost_hba',
            component: () => import('@/views/components/repost_hba.vue')
        }
            , {
            name:'Quarterly_Value_Report',
            path:'/ui/quarterly_value_report',
            component: () => import('@/components/dashboard/Quarterly_Value_Report.vue')
        }



    ]
};

export default MainRoutes;
