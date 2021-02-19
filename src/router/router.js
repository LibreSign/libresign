import SelectAction from '../middlewares/SelectAction'

const routes = [
	{
		path: '/',
		name: 'Home',
		component: () => import('../views/CreateSubscription'),
	}, {
		path: '/sign/:uuid',
		redirect: { name: OC.appConfig.libresign ? SelectAction(OC.appConfig.libresign.action) : 'Home' },
	}, {
		path: '/sign/:uuid#Sign',
		component: () => import('../views/SignPDF'),
		props: (route) => ({ uuid: route.params.uuid }),
		name: 'SignPDF',
	}, {
		path: '/sign/:uuid#Create',
		component: () => import('../views/CreateUser'),
		name: 'CreateUser',
		props: (route) => ({ params: route.params }),
	}, {
		path: '/sign/:uuid#error',
		component: () => import('../views/DefaultPageError'),
		name: 'DefaultPageError',
		props: () => ({ error: { message: OC.appConfig.libresign.errors } }),
	},
]

export default routes
