export const ProductFilter = ({ search, setSearch }: { search: string; setSearch: (s: string) => void }) => {
  return (
    <input
      type="text"
      placeholder="Search products..."
      value={search}
      onChange={(e) => setSearch(e.target.value)}
      className="w-full p-2 border rounded mb-4"
    />
  );
};
