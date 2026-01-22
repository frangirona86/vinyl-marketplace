import { createRouter, createWebHistory } from 'vue-router'

const routes = [
  {
    path: '/',
    name: 'home',
    redirect: '/vinyls'
  },
  {
    path: '/vinyls',
    name: 'vinyls',
    component: () => import('@/views/VinylListing.vue'),
    meta: { title: 'Vinyl Collection' }
  },
  {
    path: '/vinyls/:id',
    name: 'vinyl-detail',
    component: () => import('@/views/VinylDetail.vue'),
    meta: { title: 'Vinyl Detail' }
  },
  {
    path: '/search',
    name: 'search',
    component: () => import('@/views/SearchResults.vue'),
    meta: { title: 'Search' }
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) {
      return savedPosition
    } else {
      return { top: 0 }
    }
  }
})

router.beforeEach((to, from, next) => {
  document.title = to.meta.title 
    ? `${to.meta.title} | Vinyl Marketplace` 
    : 'Vinyl Marketplace'
  next()
})

export default router
