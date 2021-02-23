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
		props: (route) => ({ uuid: route.params.uuid, redirect: false }),
		name: 'SignPDF',
	}, {
		path: '/sign/:uuid#Create',
		component: () => import('../views/CreateUser'),
		name: 'CreateUser',
		props: (route) => ({
			messageToast: 'User not found for this email.',
		}),
	}, {
		path: '/sign/:uuid#error',
		component: () => import('../views/DefaultPageError'),
		name: 'DefaultPageError',
		props: (route) => ({ error: { message: OC.appConfig.libresign.errors } }),
	},
	{
		path: '/success',
		component: () => import('../views/DefaultPageSuccess'),
		name: 'DefaultPageSuccess',
	},
]

export default routes
