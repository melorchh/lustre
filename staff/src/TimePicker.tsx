import { Select } from './components';

interface TimePickerProps {
  value: string;
  onChange: (value: string) => void;
}

const HOURS = Array.from({ length: 12 }, (_, i) => String(i + 1).padStart(2, '0'));
const MINUTES = Array.from({ length: 60 }, (_, i) => String(i).padStart(2, '0'));
const PERIODS = ['AM', 'PM'];

function parseTime(value: string) {
  const [rawHour, rawMinute] = value.split(':');
  const hour = Number(rawHour);
  const minute = Number(rawMinute);
  const validHour = Number.isInteger(hour) && hour >= 0 && hour <= 23 ? hour : 9;
  const validMinute = Number.isInteger(minute) && minute >= 0 && minute <= 59 ? minute : 0;

  return {
    hour: String((validHour + 11) % 12 + 1).padStart(2, '0'),
    minute: String(validMinute).padStart(2, '0'),
    period: validHour >= 12 ? 'PM' : 'AM',
  };
}

export default function TimePicker({ value, onChange }: TimePickerProps) {
  const time = parseTime(value);

  function update(hour: string, minute: string, period: string) {
    const hour12 = Number(hour) % 12;
    const hour24 = hour12 + (period === 'PM' ? 12 : 0);
    onChange(`${String(hour24).padStart(2, '0')}:${minute}`);
  }

  return (
    <div className="ap-time-picker" role="group" aria-label="Select time">
      <svg className="ap-time-picker-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
        <circle cx="12" cy="12" r="9" />
        <path d="M12 7v5l3 2" />
      </svg>
      <Select
        value={time.hour}
        onChange={(e) => update(e.target.value, time.minute, time.period)}
        aria-label="Hour"
      >
        {HOURS.map((hour) => <option key={hour} value={hour}>{hour}</option>)}
      </Select>
      <Select
        value={time.minute}
        onChange={(e) => update(time.hour, e.target.value, time.period)}
        aria-label="Minute"
      >
        {MINUTES.map((minute) => <option key={minute} value={minute}>{minute}</option>)}
      </Select>
      <Select
        value={time.period}
        onChange={(e) => update(time.hour, time.minute, e.target.value)}
        aria-label="AM or PM"
      >
        {PERIODS.map((period) => <option key={period} value={period}>{period}</option>)}
      </Select>
    </div>
  );
}
