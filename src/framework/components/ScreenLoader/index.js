import React from 'react';
import { Flex, Spinner } from '@framework/components';
import './index.css';

const ScreenLoader = () => {
	return (
		<Flex className="hourgate-screen-loader" justify="center">
			<Spinner />
		</Flex>
	);
};

export default ScreenLoader;
