import React from 'react';
import { Controller, useController } from 'react-hook-form';
import Flex from '@framework/components/Flex';
import Label from '@framework/components/form/Label';
import { useFieldIds, fieldAria } from '@framework/components/form/fieldIds';
import '../index.css'; // form styles
import './index.css'; // switch styles

const Switch = (props) => {
	const {
		name,
		control,
		label = null,
		disabled = false,
		validations = {},
		className = '',
		extaInfo = '',
		style = {},
		onBeforeChange = null,
	} = props;
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

	return (
		<Flex
			className={
				className ? `hourgate-form-field ${className}` : 'hourgate-form-field'
			}
			style={style}
			vertical
			gap={3}
		>
			{label && (
				<Label
					text={label}
					htmlFor={ids.field}
					required={validations.required ? true : false}
				/>
			)}
			{extaInfo && <small id={ids.info}>{extaInfo}</small>}
			<Controller
				name={name}
				control={control}
				rules={validations}
				defaultValue={false}
				render={({ field }) => (
					<label className="hourgate-switch">
						<input
							type="checkbox"
							role="switch"
							{...field}
							id={ids.field}
							{...aria}
							disabled={disabled}
							checked={field.value}
							onChange={(e) => {
								// A caller can hold a change back (for example to
								// ask first): return false and set the value itself.
								if (
									onBeforeChange &&
									onBeforeChange(e.target.checked) === false
								) {
									return;
								}
								field.onChange(e.target.checked);
							}}
						/>
						<span
							className={`hourgate-switch-slider${disabled ? ' disabled' : ''}`}
						></span>
					</label>
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

export default Switch;
