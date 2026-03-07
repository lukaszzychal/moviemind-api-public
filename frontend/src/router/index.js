import { createRouter, createWebHistory } from 'vue-router'
import Layout from '@/components/Layout.vue'
import Home from '@/views/Home.vue'
import Search from '@/views/Search.vue'
import MovieDetail from '@/views/MovieDetail.vue'
import PersonDetail from '@/views/PersonDetail.vue'
import TvSeriesDetail from '@/views/TvSeriesDetail.vue'
import TvShowDetail from '@/views/TvShowDetail.vue'
import Compare from '@/views/Compare.vue'
import Generate from '@/views/Generate.vue'
import Job from '@/views/Job.vue'
import Feedback from '@/views/Feedback.vue'

const routes = [
  {
    path: '/',
    component: Layout,
    children: [
      { path: '', name: 'Home', component: Home },
      { path: 'search', name: 'Search', component: Search },
      { path: 'movies/:slug', name: 'MovieDetail', component: MovieDetail },
      { path: 'people/:slug', name: 'PersonDetail', component: PersonDetail },
      { path: 'tv-series/:slug', name: 'TvSeriesDetail', component: TvSeriesDetail },
      { path: 'tv-shows/:slug', name: 'TvShowDetail', component: TvShowDetail },
      { path: 'compare', name: 'Compare', component: Compare },
      { path: 'generate', name: 'Generate', component: Generate },
      { path: 'jobs/:id', name: 'Job', component: Job },
      { path: 'feedback', name: 'Feedback', component: Feedback },
    ],
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

export default router
