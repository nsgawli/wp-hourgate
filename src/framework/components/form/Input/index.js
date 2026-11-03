import React from 'react';
import { Controller, useController } from 'react-hook-form';
import Flex from '@framework/components/Flex';
import Label from '@framework/components/form/Label';
import { useFieldIds, fieldAria } from '@framework/components/form/fieldIds';
import '../index.css'; // form styles

const Input = ({
	name,
	control,
	label = null,
	hideLabel = false,
	validations = {},
	className = '',
	extaInfo = '',
	htmlAutoComplete = false,
	readonly = false,
	maxlength = null,
	placeholder = null,
	style = {},
}) => {
	const { fieldState } = useController({
		name,
		control,
	});
	const ids = useFieldIds();
	const aria = fieldAria(ids, {
		info: extaInfo,
		error: fieldState.error?.message,
		required: !!validations.required,
	});

	className = className ? `hourgate-form-field ${className}` : 'hourgate-form-field';

	return (
		<Flex className={className} style={style} vertical gap={3}>
			{label && (
				<Label
					text={label}
					htmlFor={ids.field}
					hidden={hideLabel}
					required={validations.required ? true : false}
				/>
			)}
			{extaInfo && <small id={ids.info}>{extaInfo}</small>}
			<Controller
				name={name}
				control={control}
				rules={validations}
				render={({ field }) => (
					<input
						{...field}
						id={ids.field}
						{...aria}
						type="text"
						readOnly={readonly}
						maxLength={maxlength}
						placeholder={placeholder}
						autoComplete={htmlAutoComplete ? 'on' : 'off'}
						onChange={(e) => {
							field.onChange(e);
						}}
					/>
				)}
			/>
			{fieldState.error && fieldState.error.message && (
				<small id={ids.error} className="hourgate-form-field-error-msg">
					{fieldState.error.message}
				</small>
			)}
		</Flex>
	);
};

export default Input;
