import { startStimulusApp } from '@symfony/stimulus-bundle';
import CopyValueController from './controllers/copy_value_controller.js';
import ModalController from './controllers/modal_controller.js';
import RelationPickerController from './controllers/relation_picker_controller.js';
import SecretVisibilityController from './controllers/secret_visibility_controller.js';

const app = startStimulusApp();
app.register('copy-value', CopyValueController);
app.register('modal', ModalController);
app.register('relation-picker', RelationPickerController);
app.register('secret-visibility', SecretVisibilityController);
