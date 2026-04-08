import { startStimulusApp } from '@symfony/stimulus-bundle';
import CopyValueController from './controllers/copy_value_controller.js';
import RelationPickerController from './controllers/relation_picker_controller.js';

const app = startStimulusApp();
app.register('copy-value', CopyValueController);
app.register('relation-picker', RelationPickerController);
