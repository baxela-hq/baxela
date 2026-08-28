import { useTranslation } from 'react-i18next';

export type tFn = (key: string, vars?: Record<string, string>) => string

export function useAppTranslation(namespace: string) {
  const { t } = useTranslation(namespace);

  const tLabel = (key: string, vars?: Record<string, string>) => {
    return t(`form.labels.${key}`, vars);
  };

  const tAction = (key: string, vars?: Record<string, string>) => {
    return t(`form.actions.${key}`, vars);
  };

  const tPlaceHolder = (key: string, vars?: Record<string, string>) => {
    return t(`form.placeholders.${key}`, vars);
  };

  const tStatus = (key: string, vars?: Record<string, string>) => {
    return t(`form.statuses.${key}`, vars);
  };

  const tTooltip = (key: string, vars?: Record<string, string>) => {
    return t(`form.tooltips.${key}`, vars);
  };

  const tHelpText = (key: string, vars?: Record<string, string>) => {
    return t(`form.help_texts.${key}`, vars);
  };

  const tMessage = (key: string, vars?: Record<string, string>) => {
    return t(`messages.${key}`, vars);
  };

  const tPageTitle = (key: string, vars?: Record<string, string>) => {
    return t(`page_titles.${key}`, vars);
  };

  const tx = (key: string, prefix: string, vars?: Record<string, string>) => {
    return t(`${prefix}.${key}`, vars);
  }

  return { t: t, tLabel, tAction, tPlaceHolder, tStatus, tTooltip, tHelpText, tMessage, tPageTitle, tx };
}
