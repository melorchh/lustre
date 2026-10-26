import { mount } from '../mount';
import ClientProfile from '../pages/ClientProfile';

const portal = (window.__LUSTRE__?.staff?.role === 'doctor' ? 'doctor' : 'admin') as 'admin' | 'doctor';

mount(() => <ClientProfile portal={portal} />);
