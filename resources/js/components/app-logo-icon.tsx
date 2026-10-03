import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 40 42" xmlns="http://www.w3.org/2000/svg">
            <path
                fillRule="evenodd"
                clipRule="evenodd"
                d="M20 1C9.507 1 1 9.507 1 20C1 30.493 9.507 39 20 39C30.493 39 39 30.493 39 20C39 9.507 30.493 1 20 1ZM20 4C28.837 4 36 11.163 36 20H25.5C25.5 16.962 23.038 14.5 20 14.5C16.962 14.5 14.5 16.962 14.5 20H4C4 11.163 11.163 4 20 4ZM20 17.5C21.381 17.5 22.5 18.619 22.5 20C22.5 21.381 21.381 22.5 20 22.5C18.619 22.5 17.5 21.381 17.5 20C17.5 18.619 18.619 17.5 20 17.5ZM20 36C11.163 36 4 28.837 4 20H14.5C14.5 23.038 16.962 25.5 20 25.5C23.038 25.5 25.5 23.038 25.5 20H36C36 28.837 28.837 36 20 36Z"
            />
        </svg>
    );
}
