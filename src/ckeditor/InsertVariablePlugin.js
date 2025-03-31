import Plugin from '@ckeditor/ckeditor5-core/src/plugin'
import { addListToDropdown, createDropdown } from '@ckeditor/ckeditor5-ui/src/dropdown/utils';
import Collection from '@ckeditor/ckeditor5-utils/src/collection';
import Model from '@ckeditor/ckeditor5-ui/src/model'

export default class InsertVariablePlugin extends Plugin {
	init() {
		const editor = this.editor;

		editor.ui.componentFactory.add('insertVariable', locale => {
			const dropdownView = createDropdown(locale);

			dropdownView.buttonView.set({
				label: 'Insert Variable',
				tooltip: true,
				withText: true,
			});

			const items = new Collection();
			const buttonModel = new Model({
				withText: true,
				label: 'Foo',
			});

			buttonModel.on('execute', () => {
				console.log('Bar executed')
				editor.model.change(writer => {
					const position = editor.model.document.selection.getFirstPosition();
					writer.insertText('{{bar}}', position);
				});
			});

			items.add({
				type: 'button',
				model: buttonModel,
			});
			addListToDropdown(dropdownView, items);

			return dropdownView;
		});
	}
}
