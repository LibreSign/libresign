import selectAction from '../middlewares/searchAction'

const config = OC.appConfig.libresign

const routes = [
	{
		path: '/sign/:uuid',
		redirect: { name: selectAction(config.action) },
	},
	{
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
		path: '/error-sign-document',
		component: () => import('../views/DefaultPageError'),
		name: 'DefaultPageError',
		props: () => ({ error: { message: config.errors } }),
	},
]

export default routes
