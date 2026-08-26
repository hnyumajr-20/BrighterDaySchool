export default function PageHeader({ title, description, actions }) {
  return (
    <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
      <div>
        <h1 className="text-3xl font-serif font-bold text-slate-900 dark:text-slate-100">{title}</h1>
        {description && <p className="text-slate-500 dark:text-slate-400">{description}</p>}
      </div>
      {actions && <div className="flex gap-3">{actions}</div>}
    </div>
  );
}
